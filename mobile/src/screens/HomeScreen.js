import { LinearGradient } from "expo-linear-gradient";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useVideoPlayer, VideoView } from "expo-video";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useLayout } from "../layout";
import {
  fetchCategory,
  fetchDetail,
  fetchHome,
  fetchTrending,
  formatBytes,
  hasVf,
  isSeries,
  peekCache,
  pickTrailer,
} from "../api";
import { colors } from "../theme";
import { useJobs } from "../useJobs";
import { loadWatchHistory } from "../watchHistory";
import {
  GlassButton,
  Icon,
  ImageWithFallback,
  Logo,
  PlayButton,
  PosterCard,
  PosterSkeleton,
  SectionTitle,
  Skeleton,
  toThumbUrl,
} from "../ui";

const GAP = 8;

const TABS = [
  { id: "trend", label: "Tendance" },
  { id: "series", label: "Séries TV", tab: "series" },
  { id: "film", label: "Film", tab: "film" },
  { id: "animation", label: "Animation", tab: "animation" },
];

const YEAR_BUCKETS = [
  { label: "Tous", test: () => true },
  { label: "2020s", test: (it) => it.year >= 2020 },
  { label: "2010s", test: (it) => it.year >= 2010 && it.year < 2020 },
  { label: "2000s", test: (it) => it.year >= 2000 && it.year < 2010 },
  { label: "1990s", test: (it) => it.year >= 1990 && it.year < 2000 },
  { label: "Autre", test: (it) => it.year < 1990 || !it.year },
];

const AUDIO = [
  { label: "Tous", test: () => true },
  { label: "Doublage français", test: (it) => hasVf(it) },
  { label: "Version originale", test: (it) => !hasVf(it) },
];

const SORTS = [
  { id: "rec", label: "Pour toi" },
  { id: "hot", label: "Le plus chaud" },
  { id: "new", label: "Dernier" },
  { id: "rate", label: "Notation" },
];

function Chip({ label, on, onPress }) {
  return (
    <Pressable onPress={onPress} style={[styles.chip, on && styles.chipOn]}>
      <Text style={[styles.chipTxt, on && styles.chipTxtOn]} numberOfLines={1}>
        {label}
      </Text>
    </Pressable>
  );
}

function ChipRow({ items, active, onPick, pad = 16 }) {
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={[styles.chipRow, { paddingHorizontal: pad }]}
    >
      {items.map((label) => (
        <Chip key={label} label={label} on={active === label} onPress={() => onPick(label)} />
      ))}
    </ScrollView>
  );
}

function Grid3({ items, onOpen, cellW, gap, pad }) {
  return (
    <View style={[styles.grid, { paddingHorizontal: pad, columnGap: gap }]}>
      {items.map((item, i) => (
        <PosterCard
          key={`${item.subjectId}-${i}`}
          item={item}
          width={cellW}
          onPress={() => onOpen(item)}
        />
      ))}
    </View>
  );
}

function PosterRow({ title, items, onOpen, loading, posterW = 122, pad = 16, gap = 8 }) {
  if (!items?.length && !loading) return null;
  return (
    <View>
      <SectionTitle style={{ marginHorizontal: pad }}>{title}</SectionTitle>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={[styles.posterRow, { paddingHorizontal: pad, gap }]}
      >
        {items?.length
          ? items.slice(0, 16).map((item, i) => (
              <PosterCard
                key={`${item.subjectId}-${i}`}
                item={item}
                width={posterW}
                onPress={() => onOpen(item)}
              />
            ))
          : [0, 1, 2, 3, 4].map((i) => <PosterSkeleton key={i} width={posterW} />)}
      </ScrollView>
    </View>
  );
}

function Top10Row({ items, onOpen, posterW = 118, pad = 16 }) {
  if (!items?.length) return null;
  return (
    <View>
      <SectionTitle style={{ marginHorizontal: pad }}>Top 10 aujourd'hui</SectionTitle>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={[styles.topRow, { paddingHorizontal: Math.max(8, pad - 6) }]}
      >
        {items.slice(0, 10).map((item, i) => (
          <Pressable
            key={`${item.subjectId}-${i}`}
            style={styles.topItem}
            onPress={() => onOpen(item)}
          >
            <Text style={[styles.rank, posterW > 130 && { fontSize: 110 }]}>{i + 1}</Text>
            <PosterCard item={item} width={posterW} showTitle={false} onPress={() => onOpen(item)} />
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

function fmtDur(sec) {
  if (!sec) return "";
  const m = Math.floor(sec / 60);
  const r = Math.floor(sec % 60);
  return `${m}:${String(r).padStart(2, "0")}`;
}

function ContinueRow({ items, onOpen, width = 168, pad = 16, gap = 8 }) {
  if (!items?.length) return null;
  const thumbH = Math.round(width * (9 / 16));
  return (
    <View>
      <SectionTitle style={{ marginHorizontal: pad }}>Continuer à regarder</SectionTitle>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={[styles.posterRow, { paddingHorizontal: pad, gap }]}
      >
        {items.map((item, i) => {
          const pos = Number(item.positionSeconds) || 0;
          const dur = Number(item.durationSeconds) || 0;
          const pct = dur > 0 ? Math.min(100, Math.round((pos / dur) * 100)) : 0;
          const ep =
            Number(item.season) > 0 && Number(item.episode) > 0
              ? `S${String(item.season).padStart(2, "0")} E${String(item.episode).padStart(2, "0")}`
              : "";
          return (
            <Pressable
              key={`${item.subjectId}-${i}`}
              style={[styles.contCard, { width }]}
              onPress={() => onOpen(item)}
            >
              <View style={[styles.contThumb, { width, height: thumbH }]}>
                <ImageWithFallback
                  source={{ uri: toThumbUrl(item.coverSmall || item.cover, 320) }}
                  style={styles.contImg}
                  iconSize={18}
                />
                <View style={[styles.contPlay, { top: Math.round(thumbH / 2 - 14), left: Math.round(width / 2 - 14) }]}>
                  <Icon name="play" size={14} color="#fff" />
                </View>
                {dur ? (
                  <View style={styles.contDur}>
                    <Text style={styles.contDurTxt}>{fmtDur(dur)}</Text>
                  </View>
                ) : null}
                {pct > 0 ? (
                  <View style={styles.contBar}>
                    <View style={[styles.contFill, { width: `${pct}%` }]} />
                  </View>
                ) : null}
              </View>
              <Text numberOfLines={1} style={styles.contTitle}>
                {item.displayTitle || item.title}
              </Text>
              {ep ? <Text style={styles.contEp}>{ep}</Text> : null}
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

function CategoryTabs({ tab, onPick, overlay }) {
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={[styles.tabRow, overlay && { paddingTop: 10 }]}
    >
      {TABS.map((t) => (
        <Pressable key={t.id} onPress={() => onPick(t.id)} style={styles.tab}>
          <Text style={[styles.tabTxt, tab === t.id && styles.tabTxtOn]}>{t.label}</Text>
          {tab === t.id ? <View style={styles.tabLine} /> : null}
        </Pressable>
      ))}
    </ScrollView>
  );
}

function BrandBar({ onOpenSearch, padTop, pad = 16, logoSize = 22, showLogo = true }) {
  return (
    <View style={[styles.brandBar, { paddingTop: padTop, paddingHorizontal: pad }]}>
      {showLogo ? <Logo size={logoSize} /> : <View />}
      <Pressable onPress={onOpenSearch} hitSlop={8} style={styles.searchBtn}>
        <Icon name="search" size={18} color="#fff" />
      </Pressable>
    </View>
  );
}

export default function HomeScreen({ active = true, onOpenItem, onPlay, onOpenFiles, onOpenSearch }) {
  const layout = useLayout();
  const seedHome = peekCache("home");
  const seedTrend = peekCache("trending:1");
  const [loading, setLoading] = useState(!seedHome && !seedTrend);
  const [homeLoading, setHomeLoading] = useState(!seedHome);
  const [error, setError] = useState("");
  const [tab, setTab] = useState("trend");
  const [hero, setHero] = useState(seedHome?.sections?.[0]?.items?.[0] || seedTrend?.items?.[0] || null);
  const [sections, setSections] = useState(seedHome?.sections || []);
  const [trending, setTrending] = useState(seedTrend?.items || []);
  const [catItems, setCatItems] = useState([]);
  const [catPage, setCatPage] = useState(1);
  const [catMore, setCatMore] = useState(false);
  const [catLoading, setCatLoading] = useState(false);
  const [genre, setGenre] = useState("Tous");
  const [yearB, setYearB] = useState("Tous");
  const [audio, setAudio] = useState("Tous");
  const [sort, setSort] = useState("rec");
  const [continueItems, setContinueItems] = useState([]);
  const [trailerUrl, setTrailerUrl] = useState("");
  const [_trailerOn, setTrailerOn] = useState(false);
  const [trailerMuted, setTrailerMuted] = useState(true);
  const trailerPlayer = useVideoPlayer(null);
  const [heroOffscreen, setHeroOffscreen] = useState(false);
  const jobs = useJobs();
  const insets = useSafeAreaInsets();
  const loadedTabs = useRef({});

  const load = useCallback(() => {
    setError("");
    if (peekCache("home") || peekCache("trending:1")) setLoading(false);

    fetchTrending(1)
      .then((data) => {
        const items = data.items || [];
        setTrending(items);
        setHero((h) => h || items[0] || null);
        setLoading(false);
      })
      .catch(() => {
        setLoading(false);
      });

    fetchHome()
      .then((data) => {
        const list = data.sections || [];
        setSections(list);
        setHero((h) => h || list[0]?.items?.[0] || null);
        setLoading(false);
        setHomeLoading(false);
      })
      .catch((e) => {
        setError(e.message || "Accueil indisponible");
        setLoading(false);
        setHomeLoading(false);
      });
  }, []);

  const loadCat = useCallback(async (tabDef, page = 1, append = false) => {
    setCatLoading(true);
    try {
      const data = await fetchCategory({ tab: tabDef.tab, page });
      const items = data.items || [];
      setCatItems((prev) => {
        if (!append) return items;
        const seen = new Set(prev.map((x) => String(x.subjectId)));
        return [...prev, ...items.filter((m) => !seen.has(String(m.subjectId)))];
      });
      setCatPage(page);
      setCatMore(items.length > 0);
      loadedTabs.current[tabDef.id] = true;
    } catch (e) {
      if (!append) setError(e.message);
      setCatMore(false);
    } finally {
      setCatLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    loadWatchHistory()
      .then((list) => setContinueItems(Array.isArray(list) ? list.slice(0, 12) : []))
      .catch(() => {});
  }, []);

  useEffect(() => {
    setTrailerUrl("");
    setTrailerOn(false);
    setTrailerMuted(true);
    if (!hero?.subjectId) return;
    const local = pickTrailer(hero);
    if (local) {
      setTrailerUrl(local);
      return;
    }
    let live = true;
    fetchDetail(hero.subjectId)
      .then((data) => {
        if (!live) return;
        const url = pickTrailer(data?.item, data, hero);
        if (url) setTrailerUrl(url);
      })
      .catch(() => {});
    return () => {
      live = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [hero?.subjectId]);

  useEffect(() => {
    if (!trailerUrl) {
      trailerPlayer.pause();
      setTrailerOn(false);
      return;
    }
    trailerPlayer.replace(trailerUrl);
    trailerPlayer.loop = true;
    trailerPlayer.muted = trailerMuted;
    if (!heroOffscreen && active) trailerPlayer.play();
    else trailerPlayer.pause();
  }, [trailerUrl, trailerMuted, heroOffscreen, active, trailerPlayer]);

  useEffect(() => {
    const off = trailerPlayer.addListener("statusChange", (e) => {
      const s = e?.status || e;
      if (s === "readyToPlay") setTrailerOn(true);
      else if (s === "error") {
        setTrailerUrl("");
        setTrailerOn(false);
      }
    });
    return () => off.remove();
  }, [trailerPlayer]);

  useEffect(() => {
    const def = TABS.find((t) => t.id === tab);
    if (def?.tab && !loadedTabs.current[def.id]) {
      setGenre("Tous");
      setYearB("Tous");
      setAudio("Tous");
      setSort("rec");
      loadCat(def);
    }
  }, [tab, loadCat]);

  const genres = useMemo(() => {
    const count = new Map();
    for (const it of catItems) {
      for (const g of it.genres || []) count.set(g, (count.get(g) || 0) + 1);
    }
    return [
      "Tous",
      ...Array.from(count.entries())
        .sort((a, b) => b[1] - a[1])
        .slice(0, 8)
        .map(([g]) => g),
    ];
  }, [catItems]);

  const filtered = useMemo(() => {
    if (tab === "trend") return [];
    const gTest = (it) => genre === "Tous" || (it.genres || []).includes(genre);
    const yTest = YEAR_BUCKETS.find((b) => b.label === yearB)?.test || (() => true);
    const aTest = AUDIO.find((a) => a.label === audio)?.test || (() => true);
    let out = catItems.filter((it) => gTest(it) && yTest(it) && aTest(it));
    if (sort === "rate") out = [...out].sort((a, b) => (b.imdbRating || 0) - (a.imdbRating || 0));
    if (sort === "new") out = [...out].sort((a, b) => (b.year || 0) - (a.year || 0));
    if (sort === "hot")
      out = [...out].sort(
        (a, b) =>
          (b.imdbRating || 0) * (b.seasonCount ? 1.1 : 1) -
          (a.imdbRating || 0) * (a.seasonCount ? 1.1 : 1)
      );
    return out;
  }, [tab, catItems, genre, yearB, audio, sort]);

  const waitingFirst = loading && !hero && !trending.length && !sections.length;

  const heroTitle = hero?.displayTitle || hero?.title || "MANDEN";
  const heroKind = hero ? (isSeries(hero) ? "Série TV" : "Film") : "";
  const heroGenres = (hero?.genres || []).slice(0, 3).join(" · ");
  const def = TABS.find((t) => t.id === tab);
  const preview = jobs.slice(0, 2);
  const popular = (trending.length ? trending : sections.flatMap((s) => s.items || [])).slice(
    0,
    16
  );
  const popularSeries = popular.filter((it) => isSeries(it));
  const popularFilms = popular.filter((it) => !isSeries(it));

  function openContinue(h) {
    onOpenItem({
      subjectId: h.subjectId,
      title: h.title,
      displayTitle: h.title,
      cover: h.cover,
      coverSmall: h.cover,
      season: Number(h.season) > 0 ? Number(h.season) : undefined,
      episode: Number(h.episode) > 0 ? Number(h.episode) : undefined,
    });
  }

  return (
    <ScrollView
      style={styles.scroll}
      contentContainerStyle={{ paddingBottom: layout.chromeBottom + insets.bottom }}
      showsVerticalScrollIndicator={false}
      scrollEventThrottle={16}
      onScroll={(e) => {
        const y = e.nativeEvent.contentOffset.y;
        setHeroOffscreen(y > layout.heroH * 0.45);
      }}
    >
      {tab === "trend" ? (
        <View style={[styles.hero, { height: layout.heroH }]}>
          <ImageWithFallback
            source={{ uri: hero?.cover || hero?.coverSmall }}
            style={StyleSheet.absoluteFill}
            iconSize={48}
          />
          {trailerUrl ? (
            <View style={styles.heroBg} pointerEvents="none">
              <VideoView
                player={trailerPlayer}
                style={styles.heroVideo}
                contentFit="cover"
              />
            </View>
          ) : null}
          <LinearGradient
            colors={[
              "rgba(0,0,0,0.58)",
              "rgba(0,0,0,0.04)",
              "transparent",
              "rgba(0,0,0,0.45)",
              "rgba(0,0,0,0.92)",
              "#000",
            ]}
            locations={[0, 0.16, 0.38, 0.62, 0.86, 1]}
            style={StyleSheet.absoluteFill}
            pointerEvents="none"
          />
          <BrandBar
            onOpenSearch={onOpenSearch}
            padTop={insets.top + 4}
            pad={layout.pad}
            logoSize={layout.isTv ? 28 : 22}
            showLogo={!layout.sideNav}
          />
          <CategoryTabs tab={tab} onPick={setTab} overlay pad={layout.pad} />
          <View style={[styles.heroContent, { paddingHorizontal: layout.pad, maxWidth: layout.isTv ? 720 : undefined }]}>
            {waitingFirst ? (
              <>
                <Skeleton width={120} height={12} />
                <Skeleton width={240} height={28} style={{ marginTop: 8 }} />
                <Skeleton width={180} height={12} style={{ marginTop: 8 }} />
              </>
            ) : (
              <>
                <View style={styles.heroKicker}>
                  {heroKind ? <Text style={styles.heroMeta}>{heroKind}</Text> : null}
                  {hero?.year ? <Text style={styles.heroMeta}> · {hero.year}</Text> : null}
                  {hero?.imdbRating ? (
                    <>
                      <Text style={styles.heroMeta}> · </Text>
                      <Icon name="star" size={11} color={colors.gold} />
                      <Text style={styles.heroMeta}> {hero.imdbRating}</Text>
                    </>
                  ) : null}
                </View>
                <Text
                  numberOfLines={2}
                  style={[
                    styles.heroTitle,
                    { fontSize: layout.titleSize, lineHeight: layout.titleSize + 6 },
                  ]}
                >
                  {heroTitle}
                </Text>
                {heroGenres ? (
                  <Text numberOfLines={1} style={styles.heroGenres}>
                    {heroGenres}
                  </Text>
                ) : null}
                <View style={styles.heroCtas}>
                  <PlayButton label="Lecture" onPress={() => hero && onPlay(hero)} style={{ minWidth: 132 }} />
                  <GlassButton
                    label="Plus d'infos"
                    icon="information-circle-outline"
                    onPress={() => hero && onOpenItem(hero)}
                  />
                </View>
              </>
            )}
          </View>
          {trailerUrl ? (
            <Pressable
              onPress={() => {
                setTrailerOn(true);
                setTrailerMuted((m) => !m);
              }}
              style={styles.muteBtn}
              hitSlop={8}
            >
              <Icon name={trailerMuted ? "volume-mute" : "volume-high"} size={16} color="#fff" />
            </Pressable>
          ) : null}
        </View>
      ) : (
        <>
          <BrandBar
            onOpenSearch={onOpenSearch}
            padTop={insets.top + 4}
            pad={layout.pad}
            logoSize={layout.isTv ? 28 : 22}
            showLogo={!layout.sideNav}
          />
          <CategoryTabs tab={tab} onPick={setTab} pad={layout.pad} />
        </>
      )}

      {tab === "trend" ? (
        <>
          {error ? (
            <View style={styles.errBox}>
              <Text style={styles.err}>{error}</Text>
              <Pressable onPress={load} hitSlop={8}>
                <Text style={styles.retry}>Réessayer</Text>
              </Pressable>
            </View>
          ) : null}

          <ContinueRow
            items={continueItems}
            onOpen={openContinue}
            width={layout.continueW}
            pad={layout.pad}
            gap={layout.gap}
          />
          <Top10Row items={popular} onOpen={onOpenItem} posterW={layout.posterW} pad={layout.pad} />
          <PosterRow
            title={popularSeries.length ? "Séries populaires" : "Populaires"}
            items={popularSeries.length ? popularSeries : popular}
            onOpen={onOpenItem}
            loading={loading && !popular.length}
            posterW={layout.posterW}
            pad={layout.pad}
            gap={layout.gap}
          />
          {popularFilms.length ? (
            <PosterRow
              title="Films populaires"
              items={popularFilms}
              onOpen={onOpenItem}
              posterW={layout.posterW}
              pad={layout.pad}
              gap={layout.gap}
            />
          ) : null}
          {sections.slice(0, 4).map((section) => (
            <PosterRow
              key={section.title}
              title={section.title}
              items={section.items || []}
              onOpen={onOpenItem}
              posterW={layout.posterW}
              pad={layout.pad}
              gap={layout.gap}
            />
          ))}
          {homeLoading && !sections.length ? (
            <PosterRow
              title="Pour toi"
              items={[]}
              onOpen={onOpenItem}
              loading
              posterW={layout.posterW}
              pad={layout.pad}
              gap={layout.gap}
            />
          ) : null}
        </>
      ) : (
        <>
          <ChipRow items={genres} active={genre} onPick={setGenre} pad={layout.pad} />
          <ChipRow items={YEAR_BUCKETS.map((b) => b.label)} active={yearB} onPick={setYearB} pad={layout.pad} />
          <ChipRow items={AUDIO.map((a) => a.label)} active={audio} onPick={setAudio} pad={layout.pad} />

          <View style={[styles.sortRow, { paddingHorizontal: layout.pad }]}>
            {SORTS.map((s) => (
              <Pressable key={s.id} onPress={() => setSort(s.id)} style={styles.sortTab}>
                <Text style={[styles.sortTxt, sort === s.id && styles.sortTxtOn]}>{s.label}</Text>
                {sort === s.id ? <View style={styles.sortLine} /> : null}
              </Pressable>
            ))}
          </View>

          {catLoading && !catItems.length ? (
            <ActivityIndicator color={colors.red} style={{ marginTop: 24 }} />
          ) : null}
          {error ? <Text style={[styles.err, { marginHorizontal: 16, marginTop: 12 }]}>{error}</Text> : null}

          {!catLoading || catItems.length ? (
            <>
              <Grid3
                items={filtered}
                onOpen={onOpenItem}
                cellW={layout.cellW}
                gap={layout.gap}
                pad={layout.pad}
              />
              {!filtered.length && !catLoading ? (
                <Text style={styles.empty}>Aucun titre avec ces filtres.</Text>
              ) : null}
              {catMore ? (
                <Pressable
                  style={styles.moreLink}
                  onPress={() => loadCat(def, catPage + 1, true)}
                  disabled={catLoading}
                >
                  <Text style={styles.moreLinkTxt}>
                    {catLoading ? "Chargement…" : "Voir plus"}
                  </Text>
                </Pressable>
              ) : null}
            </>
          ) : null}
        </>
      )}

      <Pressable style={[styles.dlHead, { marginHorizontal: layout.pad }]} onPress={() => onOpenFiles?.()}>
        <Text style={styles.dlHeadTitle}>Téléchargements</Text>
        <View style={styles.sectionAll}>
          <Text style={styles.sectionAllTxt}>Tous</Text>
          <Icon name="chevron-forward" size={14} color={colors.dim} />
        </View>
      </Pressable>
      {preview.length === 0 ? (
        <Pressable style={[styles.dlEmpty, { marginHorizontal: layout.pad }]} onPress={() => onOpenFiles?.()}>
          <Icon name="arrow-down-circle-outline" size={22} color={colors.dim} />
          <Text style={styles.dlEmptyTxt}>
            Aucun fichier. Ouvre une fiche puis touche « Télécharger ».
          </Text>
        </Pressable>
      ) : (
        <View style={[styles.dlRow, { paddingHorizontal: layout.pad }]}>
          {preview.map((item) => (
            <Pressable key={item.id} style={styles.dlCard} onPress={() => onPlay(item)}>
              <ImageWithFallback source={{ uri: toThumbUrl(item.cover, 120) }} style={styles.dlThumb} iconSize={18} />
              <View style={{ flex: 1 }}>
                <Text numberOfLines={1} style={styles.dlTitle}>
                  {item.season && item.episode
                    ? `${item.title}, S${item.season} E${item.episode}`
                    : item.title}
                </Text>
                <Text style={styles.dlSub}>
                  {item.season && item.episode ? "Épisode" : item.quality || "Fichier"}
                </Text>
                {item.status === "done" ? (
                  <View style={styles.doneRow}>
                    <Icon name="checkmark-circle" size={14} color={colors.green} />
                    <Text style={styles.done}>Terminé</Text>
                  </View>
                ) : (
                  <>
                    <View style={styles.track}>
                      <View
                        style={[styles.fill, { width: `${Math.max(3, (item.progress || 0) * 100)}%` }]}
                      />
                    </View>
                    <Text style={styles.size}>
                      {formatBytes(item.written || 0)}
                      {item.size ? ` / ${formatBytes(item.size)}` : ""}
                    </Text>
                  </>
                )}
              </View>
            </Pressable>
          ))}
        </View>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { flex: 1, backgroundColor: colors.bg },
  center: {
    flex: 1,
    backgroundColor: colors.bg,
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  hero: { width: "100%", backgroundColor: "#000", overflow: "hidden" },
  heroBg: { ...StyleSheet.absoluteFillObject },
  heroVideo: { width: "100%", height: "100%" },
  brandBar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 16,
    paddingBottom: 4,
  },
  searchBtn: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: "rgba(0,0,0,0.45)",
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "rgba(255,255,255,0.22)",
    alignItems: "center",
    justifyContent: "center",
  },
  heroContent: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 22,
    paddingHorizontal: 16,
    gap: 8,
  },
  heroKicker: { flexDirection: "row", alignItems: "center" },
  heroMeta: { color: "rgba(255,255,255,0.88)", fontSize: 13, fontWeight: "600" },
  heroTitle: {
    color: "#fff",
    fontSize: 32,
    fontWeight: "900",
    letterSpacing: 0.4,
    lineHeight: 36,
    textTransform: "uppercase",
    textShadowColor: "rgba(0,0,0,0.65)",
    textShadowRadius: 10,
  },
  heroGenres: { color: "rgba(255,255,255,0.78)", fontSize: 14, fontWeight: "500" },
  heroCtas: { flexDirection: "row", flexWrap: "wrap", alignItems: "center", gap: 10, marginTop: 8 },
  tabRow: { paddingHorizontal: 16, gap: 20, paddingBottom: 2 },
  tab: { alignItems: "center", paddingBottom: 8 },
  tabTxt: { color: "rgba(255,255,255,0.62)", fontSize: 14.5, fontWeight: "600" },
  tabTxtOn: { color: colors.text, fontWeight: "800" },
  tabLine: {
    position: "absolute",
    bottom: 0,
    width: 28,
    height: 2,
    borderRadius: 1,
    backgroundColor: colors.red,
  },
  posterRow: { paddingHorizontal: 16, gap: 8, paddingTop: 12 },
  topRow: { paddingHorizontal: 10, paddingTop: 8, alignItems: "flex-end" },
  topItem: { flexDirection: "row", alignItems: "flex-end", marginRight: 4 },
  rank: {
    fontSize: 92,
    fontWeight: "900",
    color: "#000",
    letterSpacing: -6,
    marginRight: -14,
    marginBottom: -10,
    textShadowColor: "rgba(180,180,180,0.55)",
    textShadowOffset: { width: 1, height: 0 },
    textShadowRadius: 0,
  },
  grid: {
    flexDirection: "row",
    flexWrap: "wrap",
    paddingHorizontal: 16,
    paddingTop: 12,
    columnGap: GAP,
    rowGap: 14,
  },
  moreLink: { alignItems: "center", paddingVertical: 14, marginTop: 6 },
  moreLinkTxt: { color: colors.dim, fontSize: 13.5, fontWeight: "700" },
  chipRow: { paddingHorizontal: 16, gap: 8, paddingTop: 10 },
  chip: {
    backgroundColor: "transparent",
    borderWidth: 1,
    borderColor: "#3A3A3A",
    borderRadius: 16,
    paddingHorizontal: 13,
    paddingVertical: 7,
  },
  chipOn: { backgroundColor: colors.red, borderColor: colors.red },
  chipTxt: { color: colors.muted, fontSize: 13, fontWeight: "700" },
  chipTxtOn: { color: "#fff" },
  sortRow: {
    flexDirection: "row",
    gap: 20,
    paddingHorizontal: 16,
    marginTop: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "rgba(255,255,255,0.08)",
  },
  sortTab: { alignItems: "center", paddingBottom: 8 },
  sortTxt: { color: colors.dim, fontSize: 14, fontWeight: "700" },
  sortTxtOn: { color: colors.text, fontWeight: "800" },
  sortLine: {
    position: "absolute",
    bottom: 0,
    width: "80%",
    height: 2,
    borderRadius: 1,
    backgroundColor: colors.red,
  },
  contCard: { width: 168 },
  contThumb: {
    width: 168,
    height: 94,
    borderRadius: 4,
    overflow: "hidden",
    backgroundColor: "#161616",
  },
  contImg: { width: "100%", height: "100%" },
  contPlay: {
    position: "absolute",
    alignSelf: "center",
    top: 34,
    left: 70,
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: "rgba(0,0,0,0.55)",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.7)",
    alignItems: "center",
    justifyContent: "center",
  },
  contDur: {
    position: "absolute",
    right: 6,
    bottom: 10,
    backgroundColor: "rgba(0,0,0,0.72)",
    borderRadius: 3,
    paddingHorizontal: 5,
    paddingVertical: 1,
  },
  contDurTxt: { color: "#fff", fontSize: 10, fontWeight: "700" },
  contBar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    height: 3,
    backgroundColor: "rgba(255,255,255,0.18)",
  },
  contFill: { height: 3, backgroundColor: colors.red },
  contTitle: { color: colors.text, fontSize: 12.5, fontWeight: "700", marginTop: 6 },
  contEp: { color: colors.dim, fontSize: 11.5, marginTop: 2, fontWeight: "600" },
  dlHead: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginHorizontal: 16,
    marginTop: 26,
  },
  dlHeadTitle: { color: colors.text, fontSize: 18, fontWeight: "700" },
  sectionAll: { flexDirection: "row", alignItems: "center", gap: 2 },
  sectionAllTxt: { color: colors.dim, fontSize: 13, fontWeight: "600" },
  errBox: { marginHorizontal: 16, marginTop: 14, gap: 8 },
  err: { color: "#F87171" },
  retry: { color: colors.redSoft, fontWeight: "800" },
  empty: { color: colors.dim, marginHorizontal: 16, marginTop: 12 },
  dlEmpty: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginHorizontal: 16,
    marginTop: 12,
    backgroundColor: colors.card,
    borderRadius: 8,
    padding: 14,
  },
  dlEmptyTxt: { color: colors.dim, flex: 1, fontSize: 13, lineHeight: 18 },
  dlRow: { flexDirection: "row", paddingHorizontal: 16, gap: 10, marginTop: 12 },
  dlCard: {
    flex: 1,
    backgroundColor: colors.card,
    borderRadius: 8,
    padding: 8,
    flexDirection: "row",
    gap: 8,
  },
  dlThumb: { width: 42, height: 56, borderRadius: 4, backgroundColor: "#222" },
  dlTitle: { color: colors.text, fontSize: 12, fontWeight: "700" },
  dlSub: { color: colors.dim, fontSize: 11, marginTop: 2 },
  doneRow: { flexDirection: "row", alignItems: "center", gap: 4, marginTop: 6 },
  done: { color: colors.green, fontSize: 12, fontWeight: "600" },
  track: { height: 3, backgroundColor: colors.track, borderRadius: 2, marginTop: 8 },
  fill: { height: 3, backgroundColor: colors.red, borderRadius: 2 },
  size: { color: colors.dim, fontSize: 10, marginTop: 4, textAlign: "right" },
  muteBtn: {
    position: "absolute",
    right: 16,
    bottom: 28,
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: "rgba(0,0,0,0.5)",
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "rgba(255,255,255,0.28)",
    alignItems: "center",
    justifyContent: "center",
  },
});
