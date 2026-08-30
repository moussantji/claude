import { useCallback, useEffect, useMemo, useState } from "react";
import { loadWatchHistory } from "../watchHistory";
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import * as Sharing from "expo-sharing";
import { formatBytes } from "../api";
import { useLayout } from "../layout";
import {
  cancelDownload,
  pauseDownload,
  resumeDownload,
} from "../downloadManager";
import { colors } from "../theme";
import { useJobs } from "../useJobs";
import { Icon, ImageWithFallback, toThumbUrl } from "../ui";

let FileSystem = null;
try {
  FileSystem = require("expo-file-system");
} catch {
  FileSystem = null;
}

async function exportFile(job) {
  if (!job.localUri) return;
  try {
    await Sharing.shareAsync(job.localUri, {
      mimeType: "video/mp4",
      dialogTitle: "Enregistrer dans…",
    });
  } catch {
    /* partage annulé */
  }
}

function fmtEta(bytesLeft, speed) {
  if (!speed || speed < 1024) return null;
  const s = Math.round(bytesLeft / speed);
  if (s < 60) return `${s} s`;
  const m = Math.round(s / 60);
  if (m < 60) return `${m} min`;
  return `${Math.floor(m / 60)} h ${m % 60} min`;
}

function Thumb({ item }) {
  const done = item.status === "done";
  const pct = Math.round((item.progress || 0) * 100);
  return (
    <View>
      <View style={styles.thumbWrap}>
        <ImageWithFallback source={{ uri: toThumbUrl(item.cover, 280) }} style={styles.thumb} iconSize={22} />
        {!done ? (
          <View style={styles.thumbBar}>
            <View style={[styles.thumbBarFill, { width: `${Math.max(2, pct)}%` }]} />
          </View>
        ) : null}
        <View style={styles.thumbBadge}>
          <Text style={styles.thumbBadgeTxt}>
            {done ? formatBytes(item.size) : `${pct} %`}
          </Text>
        </View>
      </View>
    </View>
  );
}

function Row({ item, onPlay }) {
  const done = item.status === "done";
  const paused = item.status === "paused";
  const left = Math.max(0, (item.size || 0) - (item.written || 0));
  const eta = item.status === "progress" ? fmtEta(left, item.speed) : null;
  const [menu, setMenu] = useState(false);

  return (
    <View style={styles.row}>
      {/* la vignette ouvre le player pour tout état : lecture, reprise, reprise DL */}
      <Pressable onPress={() => onPlay(item)}>
        <Thumb item={item} />
      </Pressable>

      <View style={styles.rowBody}>
        <Text numberOfLines={1} style={styles.title}>
          {item.season && item.episode
            ? `${item.title}  ${fmtEp(item.season, item.episode)}`
            : item.title}
        </Text>
        <View style={styles.metaRow}>
          <Icon name="layers-outline" size={13} color={colors.dim} />
          <Text style={styles.meta}>
            {item.quality || "MP4"} · {formatBytes(done ? item.size : item.written || 0)}
            {!done && item.size ? ` / ${formatBytes(item.size)}` : ""}
          </Text>
        </View>
        {done ? (
          <Text style={styles.doneTxt}>Téléchargé · prêt hors connexion</Text>
        ) : paused ? (
          <Text style={styles.pausedTxt} numberOfLines={1}>
            En pause{item.error ? ` — ${item.error}` : ""}
          </Text>
        ) : item.status === "progress" ? (
          <View style={styles.metaRow}>
            {item.speed ? (
              <>
                <Icon name="flash" size={13} color={colors.redSoft} />
                <Text style={[styles.meta, { color: colors.text, fontWeight: "700" }]}>
                  {formatBytes(item.speed)}/s
                </Text>
                <Text style={styles.meta}> · </Text>
              </>
            ) : null}
            {eta ? (
              <>
                <Icon name="time-outline" size={13} color={colors.dim} />
                <Text style={styles.meta}>{eta}</Text>
              </>
            ) : (
              <Text style={styles.meta}>connexion…</Text>
            )}
          </View>
        ) : item.status === "queued" ? (
          <Text style={styles.pausedTxt}>En file d'attente…</Text>
        ) : null}

        <View style={styles.rowActions}>
          {done ? (
            <>
              <Pressable style={styles.cta} onPress={() => onPlay(item)}>
                <Icon name="play" size={14} color={colors.playText} />
                <Text style={styles.ctaTxt}>Jouer</Text>
              </Pressable>
              <Pressable
                style={({ pressed }) => [styles.saveBtn, pressed && { opacity: 0.7 }]}
                onPress={() => exportFile(item)}
              >
                <Icon name="save-outline" size={13} color={colors.text} />
                <Text style={styles.saveBtnTxt}>Enregistrer</Text>
              </Pressable>
              <Pressable
                hitSlop={8}
                onPress={() => cancelDownload(item.id)}
                style={({ pressed }) => [styles.kebab, pressed && { opacity: 0.6 }]}
              >
                <Icon name="trash-outline" size={15} color="#F87171" />
              </Pressable>
            </>
          ) : (
            <>
              {paused ? (
                <Pressable style={styles.cta} onPress={() => resumeDownload(item.id)}>
                  <Icon name="play" size={14} color={colors.playText} />
                  <Text style={styles.ctaTxt}>Reprendre</Text>
                </Pressable>
              ) : item.status === "progress" ? (
                <Pressable
                  style={styles.ctaGhost}
                  onPress={() => pauseDownload(item.id)}
                >
                  <Icon name="pause" size={14} color={colors.text} />
                  <Text style={styles.ctaGhostTxt}>Pause</Text>
                </Pressable>
              ) : null}
              <Pressable
                hitSlop={8}
                onPress={() => setMenu(true)}
                style={({ pressed }) => [styles.kebab, pressed && { opacity: 0.6 }]}
              >
                <Icon name="ellipsis-vertical" size={16} color={colors.text} />
              </Pressable>
            </>
          )}
        </View>
      </View>
      <ActionSheet
        visible={menu}
        onClose={() => setMenu(false)}
        actions={
          paused
            ? [
                { label: "Reprendre", onPress: () => resumeDownload(item.id) },
                { label: "Supprimer", danger: true, onPress: () => cancelDownload(item.id) },
                { label: "Annuler", onPress: () => {} },
              ]
            : item.status === "progress"
              ? [
                  { label: "Pause", onPress: () => pauseDownload(item.id) },
                  { label: "Supprimer", danger: true, onPress: () => cancelDownload(item.id) },
                  { label: "Annuler", onPress: () => {} },
                ]
              : [
                  { label: "Supprimer", danger: true, onPress: () => cancelDownload(item.id) },
                  { label: "Annuler", onPress: () => {} },
                ]
        }
      />
    </View>
  );
}

function fmtDur(sec) {
  if (!sec) return "";
  const m = Math.floor(sec / 60);
  const r = Math.floor(sec % 60);
  return `${m}:${String(r).padStart(2, "0")}`;
}

function HistoryCard({ item, onPress }) {
  const pos = Number(item.positionSeconds) || 0;
  const dur = Number(item.durationSeconds) || 0;
  const pct = dur > 0 ? Math.min(100, Math.round((pos / dur) * 100)) : 0;
  const title = item.displayTitle || item.title || "";
  const ep =
    Number(item.season) > 0 && Number(item.episode) > 0
      ? `S${String(item.season).padStart(2, "0")} EP${String(item.episode).padStart(2, "0")}`
      : "";
  return (
    <Pressable style={styles.histCard} onPress={onPress}>
      <View>
        <View style={styles.histThumbWrap}>
          <ImageWithFallback
            source={{ uri: toThumbUrl(item.coverSmall || item.cover, 280) }}
            style={styles.histThumb}
            iconSize={20}
          />
          {dur ? <View style={styles.thumbBadge}><Text style={styles.thumbBadgeTxt}>{fmtDur(dur)}</Text></View> : null}
          {pct > 0 ? (
            <View style={styles.thumbBar}>
              <View style={[styles.thumbBarFill, { width: `${pct}%` }]} />
            </View>
          ) : null}
        </View>
      </View>
      {title ? <Text numberOfLines={2} style={styles.histTitle}>{title}</Text> : null}
      {ep ? <Text style={styles.histEp}>{ep}</Text> : null}
    </Pressable>
  );
}

function fmtEp(season, episode) {
  if (!season && !episode) return "";
  return `S${String(season || 0).padStart(2, "0")} EP${String(episode || 0).padStart(2, "0")}`;
}

/** Bottom-sheet d'actions (style MovieBox : lignes pleine largeur). */
function ActionSheet({ visible, onClose, actions }) {
  const sheetInsets = useSafeAreaInsets();
  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <Pressable style={styles.sheetBackdrop} onPress={onClose}>
        <Pressable style={[styles.sheet, { paddingBottom: 10 + sheetInsets.bottom }]} onPress={() => {}}>
          {actions.map((a) => (
            <Pressable
              key={a.label}
              style={styles.sheetItem}
              onPress={() => {
                onClose();
                a.onPress();
              }}
            >
              <Text style={[styles.sheetItemTxt, a.danger && { color: "#F87171" }]}>
                {a.label}
              </Text>
            </Pressable>
          ))}
        </Pressable>
      </Pressable>
    </Modal>
  );
}

/** Groupe d'épisodes d'une même série (ou fichier unique). */
function DoneGroup({ group, onPlay }) {
  const [open, setOpen] = useState(false);
  const [menuFor, setMenuFor] = useState(null); // null | "group" | job.id
  const head = group[0];
  const multi = group.length > 1;
  const total = group.reduce((s, j) => s + (j.size || 0), 0);

  const selJob = group.find((g) => g.id === menuFor) || head;

  return (
    <View style={styles.groupWrap}>
      <View style={styles.row}>
        <Pressable onPress={() => (multi ? setOpen((o) => !o) : onPlay(head))}>
          <Thumb item={head} />
        </Pressable>

        <View style={styles.rowBody}>
          <View style={styles.titleRow}>
            <Text numberOfLines={1} style={styles.title}>
              {multi
                ? head.title
                : `${head.title}${head.season || head.episode ? `  ${fmtEp(head.season, head.episode)}` : ""}`}
            </Text>
            <Pressable
              hitSlop={8}
              onPress={() => setMenuFor(multi ? "group" : head.id)}
              style={({ pressed }) => [styles.kebab, pressed && { opacity: 0.6 }]}
            >
              <Icon name="ellipsis-vertical" size={15} color={colors.text} />
            </Pressable>
          </View>
          <View style={styles.metaRow}>
            <Icon name="layers-outline" size={13} color={colors.dim} />
            <Text style={styles.meta}>
              {multi
                ? `${group.length} fichiers · ${formatBytes(total)}`
                : `${head.quality || "MP4"} · ${formatBytes(head.size)}`}
            </Text>
          </View>

          <View style={styles.rowActions}>
            {multi ? (
              <Pressable style={styles.cta} onPress={() => setOpen((o) => !o)}>
                <Icon name="list" size={14} color={colors.playText} />
                <Text style={styles.ctaTxt}>Épisodes</Text>
              </Pressable>
            ) : (
              <Pressable style={styles.cta} onPress={() => onPlay(head)}>
                <Icon name="play" size={14} color={colors.playText} />
                <Text style={styles.ctaTxt}>Jouer</Text>
              </Pressable>
            )}
            {!multi ? (
              <Pressable
                style={({ pressed }) => [styles.saveBtn, pressed && { opacity: 0.7 }]}
                onPress={() => exportFile(head)}
              >
                <Icon name="save-outline" size={13} color={colors.text} />
                <Text style={styles.saveBtnTxt}>Enregistrer</Text>
              </Pressable>
            ) : null}
          </View>
        </View>
      </View>

      {multi && open
        ? group.map((ep) => (
            <View key={ep.id} style={styles.epRow}>
              <Icon name="play-circle-outline" size={20} color={colors.redSoft} />
              <Text style={styles.epLabel}>
                {fmtEp(ep.season, ep.episode)} · {ep.quality || "?"} · {formatBytes(ep.size)}
              </Text>
              <Pressable
                hitSlop={6}
                onPress={() => onPlay(ep)}
                style={({ pressed }) => [styles.epPlay, pressed && { opacity: 0.6 }]}
              >
                <Icon name="play" size={13} color={colors.playText} />
              </Pressable>
              <Pressable hitSlop={6} onPress={() => setMenuFor(ep.id)}>
                <Icon name="trash-outline" size={15} color="#F87171" />
              </Pressable>
            </View>
          ))
        : null}

      <ActionSheet
        visible={menuFor !== null}
        onClose={() => setMenuFor(null)}
        actions={
          menuFor === "group"
            ? [
                { label: "Tout lire", icon: "play", onPress: () => onPlay(head) },
                {
                  label: `Supprimer les ${group.length} épisodes`,
                  danger: true,
                  onPress: () => group.forEach((g) => cancelDownload(g.id)),
                },
                { label: "Annuler", onPress: () => {} },
              ]
            : [
                { label: "Lecture", onPress: () => onPlay(selJob) },
                { label: "Enregistrer dans…", onPress: () => exportFile(selJob) },
                { label: "Supprimer", danger: true, onPress: () => cancelDownload(menuFor) },
                { label: "Annuler", onPress: () => {} },
              ]
        }
      />
    </View>
  );
}

export default function FilesScreen({ onPlay, onBack, onOpenItem }) {
  const jobs = useJobs();
  const insets = useSafeAreaInsets();
  const layout = useLayout();
  const [tab, setTab] = useState("dl");
  const [free, setFree] = useState(0);
  const [history, setHistory] = useState([]);

  // rechargé à chaque affichage de l'onglet
  useEffect(() => {
    let live = true;
    loadWatchHistory().then((list) => {
      if (live) setHistory(list);
    });
    const unsub = () => {
      live = false;
    };
    return unsub;
  }, []);

  const refreshFree = useCallback(() => {
    if (FileSystem?.getFreeDiskStorageAsync) {
      FileSystem.getFreeDiskStorageAsync()
        .then((v) => setFree(v))
        .catch(() => {});
    }
  }, []);

  useEffect(() => {
    refreshFree();
  }, [refreshFree, jobs.length]);

  const { actives, doneGroups, cacheSize } = useMemo(() => {
    let cacheSize = 0;
    const actives = [];
    const dones = [];
    for (const j of jobs) {
      cacheSize += j.status === "done" ? j.size || j.written || 0 : j.written || 0;
      (j.status === "done" ? dones : actives).push(j);
    }
    // regroupe les épisodes d'une même série (même subjectId)
    const bySubject = new Map();
    for (const j of dones) {
      const k = String(j.subjectId);
      if (!bySubject.has(k)) bySubject.set(k, []);
      bySubject.get(k).push(j);
    }
    const doneGroups = Array.from(bySubject.values()).map((g) =>
      [...g].sort(
        (a, b) =>
          (a.season || 0) - (b.season || 0) || (a.episode || 0) - (b.episode || 0)
      )
    );
    return { actives, doneGroups, cacheSize };
  }, [jobs]);

  const doneCount = doneGroups.reduce((s, g) => s + g.length, 0);
  const list = tab === "dl" ? actives : doneGroups;
  const listCount = tab === "dl" ? actives.length : doneCount;

  return (
    <View style={[styles.wrap, { paddingTop: insets.top + 4 }]}>
      <View style={styles.navRow}>
        {onBack ? (
          <Pressable onPress={onBack} hitSlop={10} style={({ pressed }) => [styles.backBtn, pressed && { opacity: 0.6 }]}>
            <Icon name="chevron-back" size={24} color={colors.text} />
          </Pressable>
        ) : null}
        <Text style={[styles.h1, !onBack && styles.h1Tab]}>Téléchargements</Text>
        {onBack ? <View style={styles.backSpacer} /> : null}
      </View>

      {history.length ? (
        <View style={styles.histWrap}>
          <View style={[styles.histHead, { paddingHorizontal: layout.pad }]}>
            <Text style={styles.histHeadTxt}>Historique de visionnage</Text>
            <Text style={styles.histCount}>{history.length}</Text>
          </View>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={[styles.histScroller, { paddingHorizontal: layout.pad }]}>
            {history.slice(0, 12).map((h, idx) => (
              <HistoryCard
                key={`${h.subjectId}-${idx}`}
                item={h}
                onPress={() =>
                  onOpenItem?.({
                    subjectId: h.subjectId,
                    title: h.title,
                    displayTitle: h.title,
                    cover: h.cover,
                    coverSmall: h.cover,
                    season: Number(h.season) > 0 ? Number(h.season) : undefined,
                    episode: Number(h.episode) > 0 ? Number(h.episode) : undefined,
                  })
                }
              />
            ))}
          </ScrollView>
        </View>
      ) : null}

      <View style={styles.tabs}>
        {[
          { id: "dl", label: `Téléchargements (${actives.length})` },
          { id: "done", label: `Vidéos locales (${doneCount})` },
        ].map((t) => (
          <Pressable key={t.id} style={styles.tab} onPress={() => setTab(t.id)}>
            <Text style={[styles.tabTxt, tab === t.id && styles.tabTxtOn]}>{t.label}</Text>
            {tab === t.id ? <View style={styles.tabLine} /> : null}
          </Pressable>
        ))}
      </View>

      <ScrollView
        contentContainerStyle={{ paddingHorizontal: layout.pad, paddingBottom: layout.chromeBottom + insets.bottom + 24 }}
        showsVerticalScrollIndicator={false}
      >
        {list.length ? (
          <>
            <Text style={styles.secHead}>
              {tab === "dl" ? `En cours (${actives.length})` : `Téléchargé (${listCount})`}
            </Text>
            {tab === "dl"
              ? list.map((j) => <Row key={j.id} item={j} onPlay={onPlay} />)
              : list.map((group) => (
                  <DoneGroup key={group[0].id} group={group} onPlay={onPlay} />
                ))}
          </>
        ) : (
          <View style={styles.emptyBox}>
            <View style={styles.emptyRing}>
              <Icon name="cloud-download-outline" size={34} color={colors.redSoft} />
            </View>
            <Text style={styles.emptyTitle}>
              {tab === "dl" ? "Aucun téléchargement en cours" : "Rien de téléchargé"}
            </Text>
            <Text style={styles.empty}>
              Ouvre une fiche et touche « Télécharger » pour regarder hors connexion.
            </Text>
          </View>
        )}
      </ScrollView>

      <View style={[styles.footBar, { bottom: layout.sideNav ? insets.bottom : insets.bottom + layout.tabBarH }]}>
        <Text style={styles.footTxt}>
          Cache : <Text style={styles.footVal}>{formatBytes(cacheSize) || "0 o"}</Text>
          <Text style={styles.footSep}>   |   </Text>
          <Text style={styles.footVal}>{free ? formatBytes(free) : "…"}</Text>
          <Text style={styles.footSep}> disponible</Text>
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  histWrap: { marginBottom: 6 },
  histHead: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 14,
    marginBottom: 10,
  },
  histHeadTxt: { color: colors.text, fontSize: 17, fontWeight: "800", flex: 1 },
  histCount: { color: colors.dim, fontSize: 12, fontWeight: "700" },
  histScroller: { paddingHorizontal: 14, gap: 12 },
  histCard: { width: 148 },
  histThumbWrap: {
    width: 148,
    height: 83,
    borderRadius: 8,
    overflow: "hidden",
    backgroundColor: "#161616",
  },
  histThumb: { width: "100%", height: "100%" },
  histTitle: {
    color: colors.text,
    fontSize: 13,
    fontWeight: "700",
    marginTop: 6,
    lineHeight: 17,
  },
  histEp: { color: colors.dim, fontSize: 12, marginTop: 2, fontWeight: "600" },
  navRow: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 8,
    marginBottom: 4,
  },
  backBtn: { width: 44, height: 44, alignItems: "center", justifyContent: "center" },
  backSpacer: { width: 44 },
  h1: {
    color: colors.text,
    fontSize: 20,
    fontWeight: "800",
    flex: 1,
    textAlign: "center",
  },
  h1Tab: { textAlign: "left", paddingLeft: 8, fontSize: 22, letterSpacing: -0.4 },
  tabs: {
    flexDirection: "row",
    gap: 26,
    paddingHorizontal: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "rgba(255,255,255,0.08)",
  },
  tab: { alignItems: "center", paddingBottom: 10 },
  tabTxt: { color: colors.dim, fontSize: 15.5, fontWeight: "700" },
  tabTxtOn: { color: colors.text, fontWeight: "900" },
  tabLine: {
    position: "absolute",
    bottom: 0,
    width: "70%",
    height: 3,
    borderRadius: 2,
    backgroundColor: colors.red,
  },
  section: {
    color: colors.dim,
    fontSize: 13,
    fontWeight: "700",
    marginTop: 16,
    marginBottom: 12,
  },
  secHead: {
    color: colors.text,
    fontSize: 16,
    fontWeight: "900",
    marginTop: 14,
    marginBottom: 14,
  },
  row: { flexDirection: "row", gap: 12, marginBottom: 20 },
  thumbWrap: {
    width: 138,
    height: 78,
    borderRadius: 8,
    overflow: "hidden",
    backgroundColor: "#161616",
  },
  thumb: { width: "100%", height: "100%" },
  thumbBar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    height: 3,
    backgroundColor: "rgba(255,255,255,0.18)",
  },
  thumbBarFill: { height: 3, backgroundColor: colors.red },
  thumbBadge: {
    position: "absolute",
    right: 4,
    bottom: 7,
    backgroundColor: "rgba(0,0,0,0.72)",
    borderRadius: 4,
    paddingHorizontal: 5,
    paddingVertical: 1,
  },
  thumbBadgeTxt: { color: "#fff", fontSize: 10, fontWeight: "700" },
  rowBody: { flex: 1, gap: 5 },
  titleRow: { flexDirection: "row", alignItems: "center", gap: 4 },
  title: { color: colors.text, fontSize: 13.5, fontWeight: "800", flexShrink: 1 },
  metaRow: { flexDirection: "row", alignItems: "center", gap: 5 },
  meta: { color: colors.dim, fontSize: 12.5 },
  doneTxt: { color: colors.green, fontSize: 12, fontWeight: "600" },
  pausedTxt: { color: "#FBBF24", fontSize: 12 },
  rowActions: {
    flexDirection: "row",
    alignItems: "center",
    marginTop: 4,
    gap: 8,
  },
  cta: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    backgroundColor: colors.play,
    borderRadius: 6,
    paddingHorizontal: 12,
    paddingVertical: 7,
  },
  ctaTxt: { color: colors.playText, fontWeight: "800", fontSize: 12 },
  ctaGhost: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.18)",
    borderRadius: 8,
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  ctaGhostTxt: { color: colors.text, fontWeight: "700", fontSize: 13 },
  kebab: { padding: 4 },
  saveBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 3,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.22)",
    borderRadius: 8,
    paddingHorizontal: 6,
    paddingVertical: 7,
  },
  saveBtnTxt: { color: colors.text, fontSize: 10.5, fontWeight: "700" },
  groupWrap: { marginBottom: 20 },
  epRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: "rgba(255,255,255,0.04)",
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 9,
    marginBottom: 6,
    marginLeft: 6,
  },
  epLabel: { color: colors.text, fontSize: 12.5, fontWeight: "600", flex: 1 },
  epPlay: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: colors.play,
    alignItems: "center",
    justifyContent: "center",
  },
  sheetBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.6)",
    justifyContent: "flex-end",
  },
  menuBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.6)",
    justifyContent: "flex-end",
  },
  sheet: {
    width: "100%",
    backgroundColor: "#1A1A1C",
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
    paddingTop: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "rgba(255,255,255,0.1)",
  },
  sheetItem: {
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 16,
    paddingVertical: 15,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "rgba(255,255,255,0.07)",
  },
  sheetItemTxt: { fontSize: 15, fontWeight: "700", color: colors.text },
  emptyBox: { alignItems: "center", marginTop: 46, paddingHorizontal: 30, gap: 10 },
  emptyRing: {
    width: 80,
    height: 80,
    borderRadius: 40,
    borderWidth: 2,
    borderColor: "rgba(229,9,20,0.35)",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  emptyTitle: { color: colors.text, fontSize: 16, fontWeight: "800" },
  empty: { color: colors.dim, textAlign: "center", lineHeight: 20, fontSize: 13 },
  footBar: {
    position: "absolute",
    left: 0,
    right: 0,
    alignItems: "center",
    paddingVertical: 10,
    backgroundColor: "rgba(10,10,10,0.96)",
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "rgba(255,255,255,0.07)",
  },
  footTxt: { color: colors.dim, fontSize: 13 },
  footVal: { color: colors.green, fontWeight: "800" },
  footSep: { color: "rgba(255,255,255,0.15)" },
});
