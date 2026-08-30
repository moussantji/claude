import { Audio } from "expo-av";
import { useVideoPlayer, VideoView } from "expo-video";
import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useLayout } from "../layout";
import { fetchDownloads, formatBytes, hasVf, normalizeSubtitles, pickSubtitle, storeHistory } from "../api";
import { saveWatchEntry } from "../watchHistory";
import {
  getJobsSnapshot,
  savePosition,
  startDownload,
  subscribeJobs,
} from "../downloadManager";
import { ensureRangeServer } from "../localRangeServer";
import { colors } from "../theme";
import { Icon, VfBadge } from "../ui";

export default function PlayerScreen({ item, onBack, onNext }) {
  const viewRef = useRef(null);
  const insets = useSafeAreaInsets();
  const layout = useLayout();
  const [recId, setRecId] = useState(item.id || null);
  const [playUrl, setPlayUrl] = useState(null);
  const [playMode, setPlayMode] = useState(null); // 'file' | 'range' | 'remote'
  const [error, setError] = useState("");
  const [ready, setReady] = useState(false);
  const startedKeyRef = useRef(null);
  const pendingSeekRef = useRef(0);
  const lastSavedRef = useRef(0);
  const lastHistRef = useRef(0);
  const cuesRef = useRef([]);
  const lastCueRef = useRef("");
  const [tracks, setTracks] = useState([]);
  const [trackUrl, setTrackUrl] = useState("");
  const [cue, setCue] = useState("");
  const [subsOn, setSubsOn] = useState(true);

  const player = useVideoPlayer(null);

  const jobsSnapshot = useSyncExternalStore(subscribeJobs, getJobsSnapshot);
  const rec = recId ? jobsSnapshot.find((j) => j.id === recId) || null : null;

  // valeurs dynamiques lues dans les listeners (évite de rappeler addListener)
  const dynRef = useRef({});
  dynRef.current = { subsOn, recId, item, onNext };

  /* ------------------------- démarrage / rattachement du job ------------------------ */

  useEffect(() => {
    let live = true;
    setError("");
    setReady(false);
    setPlayUrl(null);
    setPlayMode(null);
    startedKeyRef.current = null;

    (async () => {
      try {
        await Audio.setAudioModeAsync({
          playsInSilentModeIOS: true,
          staysActiveInBackground: false,
          interruptionModeAndroid: Audio.INTERRUPTION_MODE_ANDROID_DO_NOT_MIX,
          shouldDuckAndroid: true,
        }).catch(() => {});
        const record = await startDownload(item);
        if (live && record?.id) setRecId(record.id);
      } catch (e) {
        if (live) setError(e?.message || "Impossible de démarrer la lecture");
      }
    })();

    return () => {
      live = false;
      Audio.setAudioModeAsync({ playsInSilentModeIOS: false }).catch(() => {});
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [item.subjectId, item.season, item.episode, item.quality]);

  /* --------------------- choix de la source + bascule range -> file:// --------------------- */

  useEffect(() => {
    if (!rec) return;
    const target = decideSource(rec);
    if (!target) return; // pas encore lisible → UI d'attente honnête
    const key = `${target.mode}:${target.url}`;
    if (startedKeyRef.current === key) return;

    let dead = false;
    (async () => {
      if (target.mode === "range") {
        const ok = await ensureRangeServer();
        if (!ok || dead) return;
      }
      const wasStreaming =
        (playMode === "range" || playMode === "remote") && target.mode === "file" && player.currentTime > 0;
      if (wasStreaming) {
        // garder la position lors du passage au fichier complet
        pendingSeekRef.current = Math.max(pendingSeekRef.current, player.currentTime * 1000);
      } else if (!playUrl) {
        // première ouverture : reprendre à la position sauvegardée
        pendingSeekRef.current = Math.max(pendingSeekRef.current, rec.positionMs || 0);
      }
      if (dead) return;
      startedKeyRef.current = key;
      setPlayMode(target.mode);
      setPlayUrl(target.url);
      setReady(false);
    })();
    return () => {
      dead = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rec?.id, rec?.status, rec?.written, rec?.probe, rec?.playable?.url]);

  function decideSource(r) {
    if (r.status === "done" && r.localUri) return { url: r.localUri, mode: "file" };
    if (r.status === "remote" && r.remoteUrl) return { url: r.remoteUrl, mode: "remote" };
    if (r.playable) return { url: r.playable.url, mode: r.playable.mode };
    return null;
  }

  /* --------------------- application de la source au player (expo-video) ------------- */

  useEffect(() => {
    if (!playUrl) return;
    player.replace(playUrl);
    player.muted = false;
    if (pendingSeekRef.current > 0) {
      player.currentTime = pendingSeekRef.current / 1000;
      pendingSeekRef.current = 0;
    }
    player.play();
    setReady(false);
  }, [playUrl, player]);

  useEffect(() => {
    const offStatus = player.addListener("statusChange", (e) => {
      const status = e?.status || e;
      if (status === "readyToPlay") setReady(true);
      else if (status === "error") {
        setError(String(player.error?.message || player.error || "Lecture impossible"));
        setReady(false);
      }
    });
    const offTime = player.addListener("timeUpdate", ({ currentTime = 0 }) => {
      const duration = player.duration || 0;
      setReady(true);
      const d = dynRef.current;
      if (d.subsOn) {
        const next = findCue(cuesRef.current, currentTime);
        if (next !== lastCueRef.current) {
          lastCueRef.current = next;
          setCue(next);
        }
      } else if (lastCueRef.current) {
        lastCueRef.current = "";
        setCue("");
      }
      if (currentTime > 2) {
        const now = Date.now();
        if (now - lastSavedRef.current > 5000) {
          lastSavedRef.current = now;
          if (d.recId) savePosition(d.recId, Math.floor(currentTime * 1000));
          if (now - lastHistRef.current > 10000 && duration) {
            lastHistRef.current = now;
            const histEntry = {
              subjectId: String(d.item.subjectId),
              season: d.item.season || 0,
              episode: d.item.episode || 0,
              title: d.item.displayTitle || d.item.title,
              cover: d.item.cover || d.item.coverSmall || "",
              positionSeconds: Math.floor(currentTime),
              durationSeconds: Math.floor(duration),
            };
            saveWatchEntry(histEntry).catch(() => {});
            storeHistory({
              subject_id: String(d.item.subjectId),
              season: d.item.season || 0,
              episode: d.item.episode || 0,
              title: d.item.displayTitle || d.item.title,
              cover: d.item.cover || d.item.coverSmall || "",
              position_seconds: histEntry.positionSeconds,
              duration_seconds: histEntry.durationSeconds,
            }).catch(() => {});
          }
        }
      }
    });
    const offEnd = player.addListener("playToEnd", () => {
      if (dynRef.current.recId) savePosition(dynRef.current.recId, 0);
      dynRef.current.onNext?.();
    });
    return () => {
      offStatus.remove();
      offTime.remove();
      offEnd.remove();
    };
  }, [player]);

  useEffect(() => {
    let live = true;
    (async () => {
      let raw = rec?.subtitles || item.subtitles;
      if (!raw?.length && item.subjectId) {
        try {
          const pack = await fetchDownloads(item.subjectId, {
            season: item.season,
            episode: item.episode,
          });
          if (!live) return;
          raw = pack.subtitles || pack.captions || [];
        } catch {
          raw = [];
        }
      }
      if (!live) return;
      const list = normalizeSubtitles(raw);
      setTracks(list);
      const preferred = pickSubtitle(list);
      setTrackUrl(preferred?.url || "");
      setSubsOn(Boolean(preferred));
    })();
    return () => {
      live = false;
    };
  }, [rec?.id, rec?.subtitles, item.subjectId, item.season, item.episode, item.subtitles]);

  useEffect(() => {
    let live = true;
    if (!trackUrl || !subsOn) {
      cuesRef.current = [];
      setCue("");
      lastCueRef.current = "";
      return undefined;
    }
    (async () => {
      try {
        const inline = tracks.find((t) => t.url === trackUrl)?.content;
        const text = inline || (await (await fetch(trackUrl)).text());
        if (!live) return;
        cuesRef.current = parseCues(text);
      } catch {
        if (live) cuesRef.current = [];
      }
    })();
    return () => {
      live = false;
    };
  }, [trackUrl, subsOn, tracks]);

  /* ------------------------------ position & fin d'épisode ------------------------------ */

  async function goFullscreen() {
    try {
      await viewRef.current?.enterFullscreen?.();
    } catch {
      /* ignore */
    }
  }

  const label =
    rec?.season && rec?.episode
      ? `${rec.displayTitle || rec.title} · S${rec.season}E${rec.episode}`
      : rec?.displayTitle || rec?.title || item.displayTitle || item.title;

  const waitingReason = !playUrl ? waitingMessage(rec, error) : null;
  const pct = Math.round((rec?.progress || 0) * 100);

  return (
    <View style={[styles.root, { paddingTop: insets.top + 8, paddingHorizontal: layout.pad }]}>
      <Pressable onPress={onBack} style={styles.back} hitSlop={8}>
        <Icon name="chevron-back" size={22} color={colors.redSoft} />
        <Text style={styles.backText}>Retour</Text>
      </Pressable>
      <View style={styles.titleRow}>
        <Text style={styles.title} numberOfLines={2}>
          {label}
        </Text>
        {hasVf(item) || hasVf(rec) ? <VfBadge /> : null}
      </View>
      <View
        style={[
          styles.player,
          layout.playerFill || layout.isTv
            ? { flex: 1, aspectRatio: undefined, borderRadius: layout.isPhone ? 14 : 8 }
            : null,
        ]}
      >
        {playUrl ? (
          <VideoView
            ref={viewRef}
            player={player}
            style={styles.video}
            nativeControls
            contentFit="contain"
          />
        ) : (
          <View style={styles.waitBox}>
            {!error ? (
              <>
                <ActivityIndicator color={colors.red} size="small" />
                {pct > 0 ? <Text style={styles.waitPct}>{pct} %</Text> : null}
              </>
            ) : null}
            <Text style={styles.hint}>{waitingReason}</Text>
          </View>
        )}
        {playUrl && !ready && !error ? (
          <View style={styles.overlay}>
            <ActivityIndicator color={colors.red} size="small" />
            <Text style={styles.hint}>Mise en place de la lecture…</Text>
          </View>
        ) : null}
        {playUrl && subsOn && cue ? (
          <View style={styles.subBox} pointerEvents="none">
            <Text style={[styles.subTxt, layout.isTv && { fontSize: 22, lineHeight: 30 }]}>{cue}</Text>
          </View>
        ) : null}
      </View>

      {rec ? (
        <>
          <View style={styles.dlHead}>
            <Icon
              name={
                rec.status === "done"
                  ? "checkmark-circle"
                  : rec.status === "paused"
                  ? "pause-circle"
                  : "download-outline"
              }
              size={16}
              color={rec.status === "done" ? colors.green : colors.redSoft}
            />
            <Text style={styles.dlLabel}>{downloadLabel(rec)}</Text>
            {tracks.length ? (
              <Pressable
                onPress={() => {
                  if (!subsOn) {
                    const preferred = pickSubtitle(tracks);
                    setTrackUrl(preferred?.url || tracks[0].url);
                    setSubsOn(true);
                    return;
                  }
                  const idx = Math.max(0, tracks.findIndex((t) => t.url === trackUrl));
                  const next = tracks[idx + 1];
                  if (!next) {
                    setSubsOn(false);
                    setCue("");
                    return;
                  }
                  setTrackUrl(next.url);
                }}
                style={styles.ccBtn}
                hitSlop={6}
              >
                <Icon name="text" size={16} color={subsOn ? colors.redSoft : colors.dim} />
                <Text style={[styles.ccTxt, subsOn && { color: colors.redSoft }]}>
                  {subsOn ? tracks.find((t) => t.url === trackUrl)?.label || "ST" : "ST off"}
                </Text>
              </Pressable>
            ) : null}
            {playUrl ? (
              <Pressable onPress={goFullscreen} style={styles.fsBtn} hitSlop={6}>
                <Icon name="expand" size={18} color={colors.redSoft} />
              </Pressable>
            ) : null}
          </View>
          {rec.status !== "done" && rec.status !== "remote" ? (
            <>
              <View style={styles.track}>
                <View
                  style={[styles.fill, { width: `${Math.max(2, (rec.progress || 0) * 100)}%` }]}
                />
              </View>
              <Text style={styles.size}>
                {formatBytes(rec.written || 0)}
                {rec.size ? ` / ${formatBytes(rec.size)}` : ""} sur disque
                {rec.speed ? ` · ${formatBytes(rec.speed)}/s` : ""}
                {rec.probe === "tail"
                  ? " · moov en fin de fichier → lecture au téléchargement complet"
                  : ""}
              </Text>
            </>
          ) : null}
        </>
      ) : null}

      {onNext ? (
        <Pressable onPress={onNext} style={styles.nextBtn}>
          <Icon name="play-skip-forward" size={16} color={colors.playText} />
          <Text style={styles.nextText}>Épisode suivant</Text>
        </Pressable>
      ) : null}

      {error ? (
        <View style={styles.errRow}>
          <Icon name="alert-circle" size={16} color="#F87171" />
          <Text style={styles.error}>{error}</Text>
        </View>
      ) : null}
    </View>
  );
}

function parseTime(raw) {
  const m = String(raw || "")
    .trim()
    .replace(",", ".")
    .match(/(?:(\d+):)?(\d+):(\d+(?:\.\d+)?)/);
  if (!m) return 0;
  return Number(m[1] || 0) * 3600 + Number(m[2] || 0) * 60 + Number(m[3] || 0);
}

function parseCues(text) {
  if (!text) return [];
  const trimmed = String(text).replace(/^\uFEFF/, "").trim();
  if (trimmed.startsWith("{") || trimmed.startsWith("[")) {
    try {
      const json = JSON.parse(trimmed);
      const list = Array.isArray(json) ? json : json.cues || json.subtitles || json.captions || [];
      const cues = list
        .map((c) => {
          const startRaw = c.start ?? c.startTime ?? c.from ?? 0;
          const endRaw = c.end ?? c.endTime ?? c.to ?? 0;
          const start = typeof startRaw === "string" ? parseTime(startRaw) : Number(startRaw) || 0;
          const end = typeof endRaw === "string" ? parseTime(endRaw) : Number(endRaw) || 0;
          return {
            start,
            end,
            text: String(c.text || c.content || c.line || "").replace(/<[^>]+>/g, "").trim(),
          };
        })
        .filter((c) => c.text && c.end >= c.start);
      const max = cues.reduce((m, c) => Math.max(m, c.end), 0);
      if (max > 100000) return cues.map((c) => ({ ...c, start: c.start / 1000, end: c.end / 1000 }));
      return cues;
    } catch {
      /* SRT / VTT */
    }
  }
  const clean = trimmed.replace(/\r/g, "");
  const blocks = clean.split(/\n\n+/);
  const cues = [];
  for (const block of blocks) {
    const lines = block
      .split("\n")
      .map((l) => l.trim())
      .filter((l) => l && !/^WEBVTT/i.test(l) && !/^NOTE\b/.test(l) && !/^STYLE\b/.test(l));
    const timeIdx = lines.findIndex((l) => /-->/.test(l));
    if (timeIdx < 0) continue;
    const [startRaw, endRaw] = lines[timeIdx].split(/-->/);
    const body = lines
      .slice(timeIdx + 1)
      .join("\n")
      .replace(/<[^>]+>/g, "")
      .trim();
    if (!body) continue;
    cues.push({ start: parseTime(startRaw), end: parseTime(endRaw), text: body });
  }
  return cues;
}

function findCue(cues, t) {
  if (!cues?.length) return "";
  for (let i = 0; i < cues.length; i += 1) {
    const c = cues[i];
    if (t >= c.start && t <= c.end) return c.text;
  }
  return "";
}

function waitingMessage(rec, error) {
  if (error) return error;
  if (!rec) return "Préparation du téléchargement…";
  if (rec.status === "paused") return "Téléchargement en pause — reprends-le depuis l’onglet Téléchargements.";
  if (rec.probe === "tail")
    return "Téléchargement… l’index vidéo (moov) est à la fin de ce fichier : la lecture démarrera quand il sera complet.";
  return `Téléchargement… lecture dès que le fichier est lisible (${Math.round(
    (rec.progress || 0) * 100
  )} %).`;
}

function downloadLabel(rec) {
  switch (rec.status) {
    case "done":
      return "Fichier complet — lecture locale";
    case "paused":
      return "Téléchargement en pause";
    case "remote":
      return "Lecture distante (web)";
    case "error":
      return rec.error || "Erreur de téléchargement";
    default:
      return "Lecture depuis les octets déjà téléchargés (aucun 2ᵉ flux distant)";
  }
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: "#000", paddingHorizontal: 12 },
  back: { marginBottom: 8, flexDirection: "row", alignItems: "center", gap: 2 },
  backText: { color: colors.redSoft, fontSize: 16, fontWeight: "700" },
  titleRow: { flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 12 },
  title: { color: colors.text, fontSize: 18, fontWeight: "800", letterSpacing: -0.3, flex: 1 },
  subBox: {
    position: "absolute",
    left: 12,
    right: 12,
    bottom: 14,
    alignItems: "center",
  },
  subTxt: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "700",
    textAlign: "center",
    lineHeight: 22,
    textShadowColor: "rgba(0,0,0,0.9)",
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 4,
    backgroundColor: "rgba(0,0,0,0.35)",
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 4,
    overflow: "hidden",
  },
  ccBtn: { flexDirection: "row", alignItems: "center", gap: 4, paddingHorizontal: 6 },
  ccTxt: { color: colors.dim, fontSize: 12, fontWeight: "800" },
  player: {
    width: "100%",
    aspectRatio: 16 / 9,
    backgroundColor: "#111",
    borderRadius: 14,
    overflow: "hidden",
    alignItems: "center",
    justifyContent: "center",
  },
  waitPct: { color: colors.text, fontSize: 22, fontWeight: "900", letterSpacing: -0.4 },
  video: { width: "100%", height: "100%" },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(0,0,0,0.35)",
    gap: 10,
  },
  waitBox: { alignItems: "center", gap: 12, paddingVertical: 40, paddingHorizontal: 16 },
  hint: { color: colors.muted, fontSize: 13, textAlign: "center" },
  dlHead: { flexDirection: "row", alignItems: "center", gap: 6, marginTop: 16 },
  dlLabel: { color: colors.muted, fontSize: 13, flex: 1 },
  fsBtn: { paddingHorizontal: 8 },
  track: { height: 5, backgroundColor: colors.track, borderRadius: 4, marginTop: 8 },
  fill: { height: 5, backgroundColor: colors.red, borderRadius: 4 },
  size: { color: colors.dim, marginTop: 6, fontSize: 12 },
  nextBtn: {
    marginTop: 14,
    alignSelf: "flex-start",
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: colors.red,
    borderRadius: 20,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  nextText: { color: colors.playText, fontWeight: "800" },
  errRow: { flexDirection: "row", alignItems: "center", gap: 6, marginTop: 12 },
  error: { color: "#F87171", flex: 1 },
});
