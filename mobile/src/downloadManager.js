import { Platform } from "react-native";
import { fetchDetail, fetchDownloads, hasVf, normalizeSubtitles } from "./api";
import { MAX_CONCURRENT_DOWNLOADS, MOVIEBOX } from "./config";
import { probeMoovPosition } from "./mp4Probe";

const {
  UA_MODELS,
  UA_ANDROIDS,
  UA_BUILDS,
  UA_LOCALES,
  UA_CRONETS,
  UA_TTL_MS,
  UA_FALLBACK,
} = MOVIEBOX;
import {
  getPlayUrl,
  isRangeServerAvailable,
  registerLocalFile,
  unregisterLocalFile,
} from "./localRangeServer";

let FileSystem = null;
try {
  FileSystem = require("expo-file-system");
} catch {
  FileSystem = null;
}

const NATIVE = Boolean(FileSystem) && Platform.OS !== "web";
const DIR = NATIVE ? `${FileSystem.documentDirectory}downloads/` : "";

// Downloader segmenté (comme Transsnet chez MovieBox) :
// chaque bloc Range part vers un fichier .seg-N, 3 en parallèle, puis les
// blocs sont concaténés dans l'ordre dans le MP4 final. Une coupe CDN ne
export const MIN_PLAY_BYTES = 256 * 1024;

// UA MovieBox avec profil d'appareil aléatoire, régénéré chaque heure
// (même philosophie que le middleware GenerateDevice côté serveur).
const DEVICE_FILE = NATIVE ? `${FileSystem.documentDirectory}moviebox_device.json` : "";
const MOVIEBOX_UA_FALLBACK = UA_FALLBACK;
let _uaCache = null;

function pickUA(arr) {
  return arr[Math.floor(Math.random() * arr.length)];
}

async function ensureMovieboxUa() {
  if (_uaCache) return _uaCache;
  let profile = null;
  if (NATIVE && DEVICE_FILE) {
    try {
      const info = await FileSystem.getInfoAsync(DEVICE_FILE);
      if (info.exists) {
        const raw = JSON.parse(await FileSystem.readAsStringAsync(DEVICE_FILE));
        if (raw?.ua && raw?.t && Date.now() - raw.t < UA_TTL_MS) profile = raw;
      }
    } catch {
      /* ignore */
    }
  }
  if (!profile) {
    profile = {
      t: Date.now(),
      android: pickUA(UA_ANDROIDS),
      locale: pickUA(UA_LOCALES),
      model: pickUA(UA_MODELS),
      build: pickUA(UA_BUILDS),
      cronet: pickUA(UA_CRONETS),
    };
    profile.ua =
      `com.community.oneroom/50020045 (Linux; U; Android ${profile.android}; ` +
      `${profile.locale}; ${profile.model}; Build/${profile.build}; ` +
      `Cronet/${profile.cronet})`;
    if (NATIVE && DEVICE_FILE) {
      await FileSystem.writeAsStringAsync(DEVICE_FILE, JSON.stringify(profile)).catch(() => {});
    }
  }
  _uaCache = profile.ua;
  return _uaCache;
}
const PROBE_STEP = 2 * 1024 * 1024;

const JOBS_FILE = `${DIR}jobs.json`;

const jobs = new Map();
const listeners = new Set();
let snapshotCache = [];
let snapshotDirty = true;
const persistTimers = new Map();
const inflightResolve = new Map();

/* ---------------------------------- store --------------------------------- */

function touch(job) {
  job._rev = (job._rev || 0) + 1;
  snapshotDirty = true;
  notify();
}

function notify() {
  for (const fn of listeners) fn();
}

function rebuildSnapshot() {
  if (!snapshotDirty) return snapshotCache;
  snapshotCache = Array.from(jobs.values()).map((j) => publicJob(j));
  snapshotDirty = false;
  return snapshotCache;
}

export function subscribeJobs(fn) {
  listeners.add(fn);
  return () => listeners.delete(fn);
}

export function getJobsSnapshot() {
  return rebuildSnapshot();
}

export function getJob(id) {
  const j = jobs.get(id);
  return j ? publicJob(j) : null;
}

function publicJob(j) {
  return {
    id: j.id,
    title: j.displayTitle || j.title || "Téléchargement",
    cover: j.coverSmall || j.cover || null,
    subjectId: j.subjectId,
    season: j.season,
    episode: j.episode,
    quality: j.quality,
    size: j.size || 0,
    written: j.written || 0,
    progress: j.progress || 0,
    speed: j.speed || 0,
    status: j.status,
    error: j.error || null,
    positionMs: j.positionMs || 0,
    playable: playableState(j),
    localUri: j.status === "done" ? j.dest : null,
    remoteUrl: j.playUrlRemote || null, // web uniquement
    subtitles: j.subtitles || null,
  };
}

/* ------------------------------- persistance ------------------------------ */

function serializeJob(j) {
  return {
    id: j.id,
    subjectId: j.subjectId,
    title: j.title,
    displayTitle: j.displayTitle,
    cover: j.cover,
    coverSmall: j.coverSmall,
    season: j.season,
    episode: j.episode,
    quality: j.quality,
    size: j.size,
    url: j.url,
    dest: j.dest,
    status: j.status === "progress" ? "paused" : j.status,
    progress: j.progress || 0,
    written: j.written,
    probe: j.probe || null,
    nextProbeAt: j.nextProbeAt || MIN_PLAY_BYTES,
    appendedIdx: j.appendedIdx || 0,
    resumeData: j.resumeData || null,
    positionMs: j.positionMs || 0,
    error: j.error || null,
  };
}

export function schedulePersist(id) {
  if (!NATIVE) return;
  clearTimeout(persistTimers.get(id));
  persistTimers.set(
    id,
    setTimeout(() => {
      persistTimers.delete(id);
      persistAll().catch(() => {});
    }, 1200)
  );
}

export async function persistAll() {
  if (!NATIVE) return;
  try {
    await FileSystem.makeDirectoryAsync(DIR, { intermediates: true }).catch(() => {});
    const list = Array.from(jobs.values()).map(serializeJob);
    await FileSystem.writeAsStringAsync(JOBS_FILE, JSON.stringify(list));
  } catch {
    /* ignore */
  }
}

async function loadPersisted() {
  try {
    const info = await FileSystem.getInfoAsync(JOBS_FILE);
    if (!info.exists) return [];
    const raw = await FileSystem.readAsStringAsync(JOBS_FILE);
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

/* ------------------------------ ids & sources ----------------------------- */

export function downloadId({ subjectId, season, episode, quality }) {
  return `${subjectId}-${season || 0}-${episode || 0}-${quality || "auto"}`;
}

function pickQuality(downloads, wanted) {
  const list = downloads || [];
  const ranked = [...list].sort((a, b) => {
    const fa = hasVf(a) ? 1 : 0;
    const fb = hasVf(b) ? 1 : 0;
    if (fa !== fb) return fb - fa;
    return (b.resolution || 0) - (a.resolution || 0);
  });
  if (wanted) {
    const exact = ranked.find((f) => f.quality === wanted);
    if (exact) return exact;
  }
  return ranked.find((f) => (f.resolution || 0) <= 1080) || ranked[0] || null;
}

async function resolveSource(item) {
  if (item.url && item.quality) {
    return { url: item.url, quality: item.quality, size: item.size };
  }
  const key = `${item.subjectId}|${item.season}|${item.episode}`;
  if (inflightResolve.has(key)) await inflightResolve.get(key).catch(() => {});
  const p = fetchDownloads(item.subjectId, { season: item.season, episode: item.episode });
  inflightResolve.set(key, p);
  try {
    const pack = await p;
    const file = pickQuality(pack.downloads, item.quality);
    if (!file) throw new Error("Aucune source vidéo pour ce titre");
    return { url: file.url, quality: file.quality, size: file.size, subtitles: pack.subtitles };
  } finally {
    inflightResolve.delete(key);
  }
}

/* --------------------------- démarrage / récupération ---------------------- */

let initPromise = null;

export function initDownloads() {
  if (initPromise) return initPromise;
  initPromise = (async () => {
    if (!NATIVE) return;
    await ensureMovieboxUa().catch(() => {});
    await FileSystem.makeDirectoryAsync(DIR, { intermediates: true }).catch(() => {});
    const persisted = await loadPersisted();
    const known = new Set();
    for (const raw of persisted) {
      if (!raw?.id) continue;
      known.add(raw.id);
      const job = {
        ...raw,
        progress: raw.status === "done" ? 1 : raw.progress || 0,
        _rev: 0,
      };
      jobs.set(raw.id, job);
      if (job.status === "done") {
        const ok = await verifyDone(job);
        if (!ok) continue;
      } else if (raw.dest) {
        // fichier partiel encore là ? met à jour written/status
        const sz = await fileSize(raw.dest);
        if (sz > 0) {
          job.written = sz;
          if (job.size) job.progress = Math.min(0.999, sz / job.size);
        } else {
          job.written = 0;
          job.resumeData = null;
          job.progress = 0;
        }
        job.status = "paused";
      }
    }
    await recoverOrphans(known);
    // Sonde les fichiers partiels déjà présents pour permettre la lecture
    // progressive même sans reprise du téléchargement (probe persisté).
    for (const job of jobs.values()) {
      if (!job.probe && (job.written || 0) >= MIN_PLAY_BYTES) {
        job.nextProbeAt = MIN_PLAY_BYTES;
        scheduleProbe(job).catch(() => {});
      }
    }
    snapshotDirty = true;
    notify();
    persistAll().catch(() => {});
  })();
  return initPromise;
}

async function verifyDone(job) {
  const sz = await fileSize(job.dest);
  if (sz > 0 && (!job.size || Math.abs(sz - job.size) < 4 * 1024 * 1024)) {
    job.written = sz;
    job.progress = 1;
    job.status = "done";
    return true;
  }
  jobs.delete(job.id);
  await FileSystem.deleteAsync(job.dest, { idempotent: true }).catch(() => {});
  return false;
}

async function recoverOrphans(known) {
  let entries = [];
  try {
    entries = await FileSystem.readDirectoryAsync(DIR);
  } catch {
    return;
  }
  for (const name of entries) {
    const m = /^(\d+)-(\d+)-(\d+)-(.+)\.mp4$/.exec(name);
    if (!m) continue;
    const [, subjectId, season, episode, quality] = m;
    const id = `${subjectId}-${Number(season)}-${Number(episode)}-${quality}`;
    if (known.has(id)) continue;
    const dest = `${DIR}${name}`;
    const job = {
      id,
      subjectId,
      season: Number(season),
      episode: Number(episode),
      quality,
      dest,
      status: "paused",
      progress: 0,
      written: await fileSize(dest),
      title: `Récupéré · ${quality}`,
      _rev: 0,
    };
    jobs.set(id, job);
    // complète le titre en arrière-plan
    fetchDetail(subjectId)
      .then((pack) => {
        const it = pack.item;
        if (!it) return;
        job.title = it.title;
        job.displayTitle = it.displayTitle;
        job.cover = it.cover;
        job.coverSmall = it.coverSmall;
        touch(job);
        schedulePersist(job.id);
      })
      .catch(() => {});
    snapshotDirty = true;
  }
}

/* -------------------------------- utilitaires ------------------------------ */

async function fileSize(path) {
  if (!path) return 0;
  try {
    const info = await FileSystem.getInfoAsync(path);
    return info.exists ? info.size || 0 : 0;
  } catch {
    return 0;
  }
}

function playableState(j) {
  if (j.status === "done") return { url: j.dest, mode: "file" };
  if (
    j.probe === "faststart" &&
    (j.written || 0) >= MIN_PLAY_BYTES &&
    isRangeServerAvailable()
  ) {
    return { url: getPlayUrl(j.id), mode: "range" };
  }
  return null;
}

const lastTouch = new Map();
function touchThrottled(job, minMs = 350) {
  const now = Date.now();
  const last = lastTouch.get(job.id) || 0;
  if (now - last < minMs) return;
  lastTouch.set(job.id, now);
  touch(job);
}

/* ------------------------------- téléchargement ----------------------------
 * Flux unique DIRECT vers le CDN (identité MovieBox), proxy Laravel en
 * secours à chaque coupure. Le CDN direct n'a pas la coupe 30 s du proxy :
 * un flux vit des minutes ; s'il meurt (« stream was reset », stall, 429),
 * on reprend à l'octet exact avec une URL fraîche — comme le fait un
 * downloader segmenté, mais sans fichiers temporaires ni assemblage.
 */

function extractDirectUrl(proxyUrl) {
  if (!proxyUrl || !/\/api\/mv-(mp4|hevc)\?/.test(proxyUrl)) return null;
  const m = /[?&]u=([^&]+)/.exec(proxyUrl);
  if (!m) return null;
  let real = "";
  try {
    real = decodeURIComponent(m[1]);
  } catch {
    return null;
  }
  return /^https?:\/\//.test(real) ? real : null;
}

function cdnHeadersFor(url) {
  const headers = { "User-Agent": _uaCache || MOVIEBOX_UA_FALLBACK };
  // bcdnxw (/bt/) : exige Referer videodownloader.site ;
  // bcdn (/resource/) : UA seul, Referer interdit (429 sinon).
  if (/bcdnxw\.hakunaymatata\.com/.test(url || "")) {
    headers.Origin = "https://videodownloader.site";
    headers.Referer = "https://videodownloader.site/";
  }
  return headers;
}

function registerWithRangeServer(record) {
  if (!NATIVE || !record.dest) return;
  registerLocalFile(record.id, {
    path: record.dest,
    totalSize: record.size || 0,
    getWritten: () => fileSize(record.dest),
    isComplete: () => Promise.resolve(jobs.get(record.id)?.status === "done"),
  });
}

async function startStream(record) {
  jobs.set(record.id, record);
  record.status = "progress";
  touch(record);

  const watchdog = setInterval(() => {
    if (record._lastWritten === record.written) {
      record._stalled = true;
    }
    record._lastWritten = record.written;
  }, 12000);

  let failStreak = 0;
  try {
    for (let round = 0; ; round += 1) {
      if (record.status !== "progress") break;
      record._stalled = false;
      record._lastWritten = record.written;
      record.speed = 0;
      record._tickAt = null;
      record._tickBytes = null;

      let written = await fileSize(record.dest);
      if (record.size && written > record.size + 2 * 1024 * 1024) {
        // sur-dépassement (zombie d'une ronde passée) -> ingérable sans seek
        if ((record._resets || 0) >= 1) {
          record.status = "paused";
          record.error = "Fichier incohérent — relance le téléchargement";
          touch(record);
          return;
        }
        record._resets = 1;
        record.written = 0;
        record.progress = 0;
        record.probe = null;
        record.nextProbeAt = MIN_PLAY_BYTES;
        await FileSystem.deleteAsync(record.dest, { idempotent: true }).catch(() => {});
        written = 0;
        console.log("[DL] fichier sur-dimensionné -> repart de zéro");
      }
      const roundStartWritten = written;
      record.written = written;
      if (record.size && written >= record.size - 4096) {
        console.log("[DL] déjà complet au démarrage de la ronde");
        break; // complet
      }

      const useDirect = round % 2 === 0;
      const target = useDirect ? extractDirectUrl(record.url) || record.url : record.url;
      const headers = { ...(useDirect ? cdnHeadersFor(target) : {}) };
      // Format expo : nombre SEUL en chaîne — le natif construit lui-même
      // l'en-tête Range ("bytes=N-"). Toute autre forme => NumberFormatException
      // côté Android et reprise impossible.
      const resumeData = written > 0 ? String(written) : undefined;
      console.log(`[DL] flux ronde${round} ${useDirect ? "direct" : "proxy"} depuis ${written} o`);

      const task = FileSystem.createDownloadResumable(
        target,
        record.dest,
        { headers },
        ({ totalBytesWritten }) => {
          // monotone : le natif renvoie déjà la position CUMULÉE dans le fichier
          const now = Date.now();
          const prev = record.written || 0;
          record.written = Math.max(prev, totalBytesWritten || 0);

          // vitesse lissée (moyenne mobile exponentielle)
          if (!record._tickAt) {
            record._tickAt = now;
            record._tickBytes = record.written;
            record.speed = 0;
          } else {
            const dt = now - record._tickAt;
            if (dt >= 400) {
              const db = record.written - (record._tickBytes || 0);
              if (db >= 0) {
                const inst = (db * 1000) / dt;
                record.speed = Math.round(
                  (record.speed || 0) * 0.6 + inst * 0.4
                );
              }
              record._tickAt = now;
              record._tickBytes = record.written;
            }
          }

          record.progress = record.size ? Math.min(0.999, record.written / record.size) : 0;
          scheduleProbe(record);
          touchThrottled(record, 350);
          schedulePersist(record.id);
        },
        resumeData
      );
      record.task = task;

      try {
        const res = resumeData
          ? await withStallGuard(task.resumeAsync(), record)
          : await withStallGuard(task.downloadAsync(), record);
        delete record.task;
        const finalSize = await fileSize(res?.uri || record.dest);
        if (finalSize <= 0) throw new Error("Fichier vide");
        if (record.size && Math.abs(finalSize - record.size) > 2 * 1024 * 1024) {
          throw new Error(`TAILLE:${finalSize}`);
        }
        finish(record);
        return;
      } catch (e) {
        delete record.task;
        const msg = String(e?.message || e || "");
        if (record.status !== "progress") return; // paused/error/cancel
        if (/^TAILLE:/.test(msg)) {
          // reprise corrompue -> repart de zéro une fois max
          if ((record._resets || 0) >= 1) throw new Error("Taille finale incohérente");
          record._resets = 1;
          record.written = 0;
          record.progress = 0;
          record.probe = null;
          record.nextProbeAt = MIN_PLAY_BYTES;
          await FileSystem.deleteAsync(record.dest, { idempotent: true }).catch(() => {});
          continue;
        }
        // 416 = Range au-delà de la fin : le fichier est déjà complet
        if (/416/.test(msg) && record.size && record.written >= record.size - 4096) {
          finish(record);
          return;
        }
        console.log(`[DL] coupe (${msg.slice(0, 60)}) -> reprise`);
        // aucune progression depuis le début de la ronde ?
        if (record.written <= roundStartWritten) failStreak += 1;
        else failStreak = 0;
        if (failStreak >= 8) {
          // réseau indisponible : mise en pause (bouton Reprendre), pas d'état erreur
          record.status = "paused";
          record.error = "Connexion indisponible — reprise possible";
          touch(record);
          persistAll().catch(() => {});
          return;
        }
        await sleep(Math.min(600 * (round + 1), 30000));
        // URL signée peut expirer entre deux rondes
        try {
          const fresh = await resolveSource(record);
          record.url = fresh.url;
          record.size = fresh.size || record.size;
          registerWithRangeServer(record);
        } catch {
          /* garde l'URL courante */
        }
      }
    }
    if (record.status === "progress") {
      const sz = await fileSize(record.dest);
      if (record.size && Math.abs(sz - record.size) <= 2 * 1024 * 1024) finish(record);
      else throw new Error("Taille finale incohérente");
    }
  } catch (e) {
    if (record.status === "progress") {
      // un échec réseau n'est pas une fin : état PAUSE, l'utilisateur reprend
      record.status = "paused";
      record.error = String(e?.message || e || "").slice(0, 160) || undefined;
      touch(record);
      persistAll().catch(() => {});
    }
  } finally {
    clearInterval(watchdog);
  }
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function finish(record) {
  record.status = "done";
  record.progress = 1;
  record.written = record.size || record.written;
  record.probe = "complete";
  record.error = null;
  record.task = null;
  record._retries = 0;
  touch(record);
  persistAll().catch(() => {});
  console.log(`[DL] TERMINÉ: ${record.id} (${record.written} o)`);
}

// Si aucun octet n'arrive pendant 30 s, on abandonne la tentative courante
// (la reprise se fera à l'octet exact au tour suivant).
function withStallGuard(promise, record) {
  return new Promise((resolve, reject) => {
    let done = false;
    const check = setInterval(() => {
      if (done) {
        clearInterval(check);
        return;
      }
      if (record._stalled || record.status !== "progress") {
        done = true;
        clearInterval(check);
        // SANS pause native, le téléchargement continue d'écrire en zombie
        // pendant que la ronde suivante repart -> fichier trop grand.
        record.task?.pauseAsync?.().catch(() => {});
        reject(new Error("stall"));
      }
    }, 4000);
    promise.then(
      (v) => {
        done = true;
        clearInterval(check);
        resolve(v);
      },
      (e) => {
        done = true;
        clearInterval(check);
        reject(e);
      }
    );
  });
}

/* --------------------------- concurrence (file) --------------------------- */
/* Limite le nombre de flux réseau simultanés pour ne pas saturer le réseau  */
/* ni le disque. Les jobs au-delà du plafond attendent en file (« queued »). */

let activeStreams = 0;
const pending = new Map();

function pump() {
  while (activeStreams < MAX_CONCURRENT_DOWNLOADS && pending.size) {
    const [id, record] = pending.entries().next().value;
    pending.delete(id);
    activeStreams += 1;
    if (record.status === "queued") record.status = "progress";
    startStream(record)
      .catch(() => {})
      .finally(() => {
        activeStreams = Math.max(0, activeStreams - 1);
        record._streamActive = false;
        touch(record);
        pump();
      });
  }
}

function enqueueStream(record) {
  if (record._streamActive) return;
  record._streamActive = true;
  if (activeStreams < MAX_CONCURRENT_DOWNLOADS) {
    activeStreams += 1;
    startStream(record)
      .catch(() => {})
      .finally(() => {
        activeStreams = Math.max(0, activeStreams - 1);
        record._streamActive = false;
        pump();
      });
  } else {
    pending.set(record.id, record);
    record.status = "queued";
    touch(record);
  }
}

export async function startDownload(item, onProgress) {
  await initDownloads();
  if (item.id) {
    const pre = jobs.get(item.id);
    if (pre?.status === "done") {
      emitTo(onProgress, pre);
      return pre;
    }
  }
  console.log("[DL] startDownload", JSON.stringify({
    subjectId: item.subjectId, season: item.season, episode: item.episode, quality: item.quality,
  }));
  const { url, quality, size, subtitles } = await resolveSource(item);
  const id = downloadId({
    subjectId: item.subjectId, season: item.season, episode: item.episode, quality,
  });

  const existing = jobs.get(id);
  if (existing?.status === "done") {
    emitTo(onProgress, existing);
    return existing;
  }

  const record =
    existing ||
    {
      id,
      subjectId: item.subjectId,
      title: item.title,
      displayTitle: item.displayTitle,
      cover: item.cover,
      coverSmall: item.coverSmall,
      season: item.season,
      episode: item.episode,
      quality,
      size,
      url,
      status: "progress",
      progress: 0,
      written: 0,
      probe: null,
      nextProbeAt: MIN_PLAY_BYTES,
      resumeData: null,
      positionMs: 0,
      _rev: 0,
    };

  record.url = url;
  record.size = size || record.size;
  record.quality = quality;
  record.subtitles = normalizeSubtitles(subtitles || item.subtitles);

  if (!NATIVE) {
    record.playUrlRemote = url;
    record.status = "remote";
    jobs.set(id, record);
    touch(record);
    emitTo(onProgress, record);
    return record;
  }

  const dest = record.dest || `${DIR}${id.replace(/[^a-zA-Z0-9._-]/g, "_")}.mp4`;
  record.dest = dest;

  const writtenOnDisk = await fileSize(dest);
  if (!record.written) record.written = writtenOnDisk;

  registerWithRangeServer(record);

  if (record.status !== "done" && record.size && record.written >= record.size - 4096) {
    finish(record);
    emitTo(onProgress, record);
    return record;
  }

  enqueueStream(record);
  emitTo(onProgress, record);
  return record;
}

/* ---------------------------------- sonde ---------------------------------- */

async function scheduleProbe(record) {
  if (record.probe) return;
  if ((record.written || 0) < (record.nextProbeAt || MIN_PLAY_BYTES)) return;
  record.nextProbeAt = (record.written || 0) + PROBE_STEP;
  const verdict = await probeMoovPosition(record.dest, record.written);
  if (!record.probe && (verdict === "faststart" || verdict === "tail")) {
    record.probe = verdict;
    touch(record);
    schedulePersist(record.id);
  }
}

/* ------------------------------ contrôle (UI) ------------------------------ */

export async function pauseDownload(id) {
  const j = jobs.get(id);
  if (!j || (j.status !== "progress" && j.status !== "queued")) return;
  pending.delete(id);
  j.status = "paused";
  j.speed = 0;
  j._streamActive = false;
  try {
    await j.task?.pauseAsync?.();
  } catch {
    /* ignore */
  }
  delete j.task;
  touch(j);
  persistAll().catch(() => {});
}

export async function resumeDownload(id) {
  const j = jobs.get(id);
  if (!j || j.status === "done" || j.status === "progress" || pending.has(id)) return;
  await startDownload(
    { subjectId: j.subjectId, season: j.season, episode: j.episode, quality: j.quality },
    null
  );
}

export async function cancelDownload(id) {
  const j = jobs.get(id);
  if (!j) return;
  pending.delete(id);
  try {
    await j.task?.pauseAsync?.();
  } catch {
    /* ignore */
  }
  j.status = "cancelled";
  try {
    await j.task?.pauseAsync?.();
  } catch {
    /* ignore */
  }
  jobs.delete(id);
  unregisterLocalFile(id);
  snapshotDirty = true;
  notify();
  if (NATIVE && j.dest) {
    await FileSystem.deleteAsync(j.dest, { idempotent: true }).catch(() => {});
  }
  persistAll().catch(() => {});
}

export function savePosition(id, positionMs) {  const j = jobs.get(id);
  if (!j) return;
  j.positionMs = Math.max(0, Math.floor(positionMs || 0));
  schedulePersist(id);
}

/* --------------------------------- helpers --------------------------------- */

function emitTo(onProgress, record) {
  onProgress?.({
    id: record.id,
    progress: record.progress,
    status: record.status === "remote" ? "progress" : record.status,
    written: record.written,
    playUrl: playableState(record)?.url || (record.playUrlRemote ?? null),
    localUri: record.status === "done" ? record.dest : null,
    canPlayLocal: Boolean(playableState(record)),
  });
}
