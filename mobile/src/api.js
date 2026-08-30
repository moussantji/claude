import { Platform } from "react-native";
import { API_BASE } from "./config";

let FileSystem = null;
if (Platform.OS !== "web") {
  try {
    FileSystem = require("expo-file-system");
  } catch {
    FileSystem = null;
  }
}

const TOKEN_FILE = `${FileSystem?.documentDirectory || ""}.auth_token`;

let authToken = null;

export function setAuthToken(token) {
  authToken = token || null;
}

export function getAuthToken() {
  return authToken;
}

export async function initAuth() {
  if (!FileSystem) return null;
  try {
    const info = await FileSystem.getInfoAsync(TOKEN_FILE);
    if (!info.exists) return null;
    const raw = await FileSystem.readAsStringAsync(TOKEN_FILE);
    const token = String(raw || "").trim();
    if (token) {
      authToken = token;
      return token;
    }
  } catch {
    /* ignore */
  }
  return null;
}

export async function persistToken(token) {
  setAuthToken(token);
  if (!FileSystem) return;
  try {
    if (!token) {
      await FileSystem.deleteAsync(TOKEN_FILE, { idempotent: true });
    } else {
      await FileSystem.writeAsStringAsync(TOKEN_FILE, token);
    }
  } catch {
    /* ignore */
  }
}

function qs(params = {}) {
  const parts = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== "")
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
  return parts.length ? `?${parts.join("&")}` : "";
}

function authError(message) {
  const err = new Error(message || "Non connecté");
  err.code = "AUTH";
  return err;
}

async function request(path, { method = "GET", body } = {}) {
  const headers = { Accept: "application/json" };
  if (body) headers["Content-Type"] = "application/json";
  if (authToken) headers.Authorization = `Bearer ${authToken}`;

  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  const isJson = (res.headers.get("content-type") || "").includes("application/json");
  const json = isJson ? await res.json().catch(() => ({})) : {};
  if (!res.ok) {
    // L'API renvoie 500 "Route [login] not defined." quand on appelle
    // une route authentifiée sans token valide.
    const msg = String(json?.message || "");
    const isAuth =
      res.status === 401 ||
      res.status === 403 ||
      /route \[login\]/i.test(msg) ||
      /unauthenticated|non authentifié|token invalide/i.test(msg);
    if (isAuth) {
      throw authError("Non connecté");
    }
    const detail = msg || (isJson ? "" : await res.text().catch(() => "")).slice(0, 200);
    throw new Error(detail || `Erreur API ${res.status}`);
  }
  return json.data ?? json;
}

const mem = new Map();

export function peekCache(key) {
  return mem.get(key)?.data ?? null;
}

function remember(key, data) {
  mem.set(key, { t: Date.now(), data });
  return data;
}

async function cached(key, path) {
  const data = await request(path);
  return remember(key, data);
}

export const fetchHome = () => cached("home", "/home");
export const fetchTrending = (page = 1) => cached(`trending:${page}`, `/trending${qs({ page })}`);
export const fetchHistory = () => request("/history");
export const fetchCategory = (params = {}) =>
  cached(`category:${JSON.stringify(params)}`, `/category${qs(params)}`);
export const fetchDetail = (subjectId) => cached(`detail:${subjectId}`, `/detail${qs({ subjectId })}`);
export const fetchItem = (subjectId) => request(`/item${qs({ subjectId })}`);
export const searchTitles = (q, page = 1) => request(`/search${qs({ q, page })}`);

/** Autocomplétion (titres). Réponse tolérante : strings ou objets. */
export async function fetchSuggest(q) {
  const data = await request(`/suggest${qs({ q })}`);
  const raw = data.suggestions || data.items || data || [];
  if (!Array.isArray(raw)) return [];
  return raw
    .map((s) => (typeof s === "string" ? { title: s } : s))
    .filter((s) => s && (s.title || s.name || s.label))
    .map((s) => ({
      title: s.title || s.name || s.label,
      subjectId: s.subjectId || s.id || null,
    }));
}

export async function fetchFavorites() {
  const data = await request("/favorites");
  return data.favorites || data.items || data || [];
}

export async function addFavorite(subjectId) {
  return request("/favorites", { method: "POST", body: { subjectId } });
}

export async function removeFavorite(subjectId) {
  return request(`/favorites/${subjectId}`, { method: "DELETE" });
}

const dlCache = new Map();
const DL_TTL = 120 * 1000;

export function fetchDownloads(subjectId, { season, episode, language, lang } = {}) {
  const key = JSON.stringify({ subjectId, season, episode, language, lang });
  const hit = dlCache.get(key);
  if (hit && Date.now() - hit.t < DL_TTL) return Promise.resolve(hit.data);
  return request(`/downloads${qs({ subjectId, season, episode, language, lang })}`).then((data) => {
    dlCache.set(key, { t: Date.now(), data });
    return data;
  });
}

export const fetchMe = () => request("/auth/me");

export async function storeHistory(payload) {
  return request("/history", { method: "POST", body: payload });
}

export async function login(email, password) {
  const data = await request("/auth/login", {
    method: "POST",
    body: { email, password },
  });
  const token = data.token || data.access_token || data.user?.token;
  if (!token) throw new Error("Réponse de connexion inattendue");
  await persistToken(token);
  return data;
}

export async function logout() {
  await persistToken(null);
}

export async function register(email, password, name) {
  const data = await request("/auth/register", {
    method: "POST",
    body: { email, password, name },
  });
  const token = data.token || data.access_token || data.user?.token;
  if (token) {
    await persistToken(token);
    return data;
  }
  return data;
}

export function formatBytes(bytes) {
  if (!bytes && bytes !== 0) return "";
  const units = ["o", "Ko", "Mo", "Go"];
  let n = bytes;
  let i = 0;
  while (n >= 1024 && i < units.length - 1) {
    n /= 1024;
    i += 1;
  }
  return `${n.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

export function isSeries(item) {
  if (!item) return false;
  return item.subjectType === 2 || item.isSeries === true || Number(item.seasonCount) > 0;
}

function asHttp(v) {
  if (typeof v !== "string") return "";
  const s = v.trim();
  return /^https?:\/\//i.test(s) ? s : "";
}

/** Première URL vidéo de bande-annonce trouvée (mp4/m3u8), pas YouTube. */
export function pickTrailer(...objs) {
  const keys = [
    "trailerUrl",
    "trailer_url",
    "trailer",
    "previewUrl",
    "preview_url",
    "teaserUrl",
    "teaser_url",
    "teaser",
    "promoUrl",
    "promo_url",
    "bannerTrailer",
    "heroTrailer",
  ];
  for (const o of objs) {
    if (!o || typeof o !== "object") continue;
    for (const k of keys) {
      const raw = o[k];
      const url = asHttp(typeof raw === "string" ? raw : raw?.url || raw?.src || "");
      if (url && !/youtube\.com|youtu\.be|vimeo\.com/i.test(url)) return url;
    }
    const nested = o.trailer || o.preview || o.teaser;
    const nestedUrl = asHttp(nested?.url || nested?.src || "");
    if (nestedUrl && !/youtube\.com|youtu\.be/i.test(nestedUrl)) return nestedUrl;
    const list = o.trailers || o.videos || o.previews;
    if (Array.isArray(list)) {
      for (const t of list) {
        const kind = String(t?.type || t?.kind || t?.name || "").toLowerCase();
        const url = asHttp(typeof t === "string" ? t : t?.url || t?.src || "");
        if (!url || /youtube\.com|youtu\.be/i.test(url)) continue;
        if (!kind || /trail|teaser|preview|promo/i.test(kind)) return url;
      }
    }
  }
  return "";
}

const FR_RE = /\b(fr|fra|fre|fran[cç]ais|french|vf|v\.f\.?|vostfr)\b/i;

function langBits(obj) {
  if (!obj) return [];
  if (typeof obj === "string") return [obj];
  if (typeof obj !== "object") return [];
  const nested = [obj.languages, obj.audios, obj.audioLanguages, obj.dubs, obj.voices, obj.seVoList];
  const direct = [
    obj.language,
    obj.lang,
    obj.lan,
    obj.audio,
    obj.audioLanguage,
    obj.audio_language,
    obj.dub,
    obj.dubbing,
    obj.seVo,
    obj.vo,
    obj.voice,
    obj.label,
    obj.name,
  ];
  const out = [];
  for (const v of direct) {
    if (typeof v === "string") out.push(v);
    else if (v && typeof v === "object") out.push(v.language || v.lang || v.name || v.label || "");
  }
  for (const list of nested) {
    if (!Array.isArray(list)) continue;
    for (const v of list) out.push(...langBits(v));
  }
  return out.filter(Boolean);
}

export function isFrenchLang(value) {
  if (value === true) return true;
  if (typeof value === "string") return FR_RE.test(value);
  if (!value || typeof value !== "object") return false;
  if (value.french === true || value.vf === true || value.hasFrench === true) return true;
  return langBits(value).some((s) => FR_RE.test(s));
}

export function hasVf(item) {
  return isFrenchLang(item);
}

export function audioLangKey(file) {
  if (isFrenchLang(file)) return "vf";
  const raw = langBits(file).join(" ").trim();
  if (!raw) return "vo";
  if (/\b(en|eng|english|anglais|vo|vost|original|ov)\b/i.test(raw)) return "vo";
  return raw.toLowerCase().slice(0, 32);
}

export function audioLangLabel(fileOrKey) {
  const key = typeof fileOrKey === "string" ? fileOrKey : audioLangKey(fileOrKey);
  if (key === "vf" || isFrenchLang(fileOrKey)) return "VF";
  if (key === "vo") return "VO";
  if (typeof fileOrKey !== "string") {
    const raw = langBits(fileOrKey).join(" ").trim();
    if (raw) return raw.charAt(0).toUpperCase() + raw.slice(1);
  }
  return String(key || "VO").toUpperCase();
}

export function listAudioLangs(files, pack) {
  const seen = new Map();
  for (const f of files || []) {
    const key = audioLangKey(f);
    if (!seen.has(key)) seen.set(key, { key, label: audioLangLabel(f) });
  }
  const extra = pack?.languages || pack?.audios || pack?.audioLanguages || pack?.item?.languages;
  if (Array.isArray(extra)) {
    for (const x of extra) {
      const obj = typeof x === "string" ? { language: x } : x;
      const key = audioLangKey(obj);
      if (!seen.has(key)) seen.set(key, { key, label: audioLangLabel(obj) });
    }
  }
  if (!seen.size && hasVf(pack?.item || pack)) seen.set("vf", { key: "vf", label: "VF" });
  const arr = Array.from(seen.values());
  arr.sort((a, b) => (a.key === "vf" ? -1 : b.key === "vf" ? 1 : a.label.localeCompare(b.label, "fr")));
  return arr;
}

export function preferredAudioKey(files, pack) {
  const langs = listAudioLangs(files, pack);
  return langs.find((l) => l.key === "vf")?.key || langs[0]?.key || "vf";
}

export function normalizeSubtitles(raw) {
  if (!raw) return [];
  const list = Array.isArray(raw) ? raw : raw.subtitles || raw.captions || raw.tracks || [];
  const out = [];
  const seen = new Set();
  for (const s of list) {
    const url = asHttp(
      typeof s === "string" ? s : s?.url || s?.src || s?.file || s?.subtitleUrl || s?.uri || ""
    );
    if (url && seen.has(url)) continue;
    if (url) seen.add(url);
    const language =
      typeof s === "string"
        ? ""
        : String(s.language || s.lang || s.lan || s.code || s.label || s.name || "");
    const french = isFrenchLang(language) || isFrenchLang(s);
    const content = typeof s === "object" ? s.content || s.text || s.srt || s.vtt || "" : "";
    if (!url && !content) continue;
    out.push({
      url: url || `inline:${out.length}`,
      language,
      french,
      content: typeof content === "string" ? content : "",
      label: french ? "Français" : language ? String(language) : "Sous-titres",
    });
  }
  out.sort((a, b) => Number(b.french) - Number(a.french));
  return out;
}

export function pickSubtitle(list) {
  const tracks = normalizeSubtitles(list);
  return tracks.find((t) => t.french) || tracks[0] || null;
}
