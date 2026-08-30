import { LinearGradient } from "expo-linear-gradient";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { fetchCategory, fetchSuggest, hasVf, isSeries, peekCache, searchTitles } from "../api";
import { useLayout } from "../layout";
import { colors } from "../theme";
import { Icon, ImageWithFallback, Logo, PosterCard, RatingBadge, SearchField, VfBadge } from "../ui";

const GAP = 8;

const TYPES = [
  { id: "all", label: "Tous" },
  { id: "film", label: "Films" },
  { id: "series", label: "Séries" },
  { id: "anim", label: "Animation" },
];

const YEAR_BUCKETS = [
  { label: "Tous", test: () => true },
  { label: "2020s", test: (it) => it.year >= 2020 },
  { label: "2010s", test: (it) => it.year >= 2010 && it.year < 2020 },
  { label: "2000s", test: (it) => it.year >= 2000 && it.year < 2010 },
  { label: "1990s", test: (it) => it.year >= 1990 && it.year < 2000 },
  { label: "Autre", test: (it) => !it.year || it.year < 1990 },
];

const AUDIO = [
  { id: "all", label: "Tous" },
  { id: "vf", label: "V.F." },
  { id: "vo", label: "V.O." },
];

const SORTS = [
  { id: "rec", label: "Pertinence" },
  { id: "rate", label: "Note" },
  { id: "new", label: "Récent" },
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

function matchesType(item, type) {
  if (type === "all") return true;
  if (type === "series") return isSeries(item);
  if (type === "film") return !isSeries(item);
  if (type === "anim") {
    return (item.genres || []).some((g) => /anim/i.test(String(g)));
  }
  return true;
}

export default function SearchScreen({ onOpenItem, active = true }) {
  const layout = useLayout();
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(false);
  const [items, setItems] = useState([]);
  const [error, setError] = useState("");
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [catItems, setCatItems] = useState(() => peekCache("category:{}")?.items || []);
  const [_catLoading, setCatLoading] = useState(!peekCache("category:{}"));
  const [type, setType] = useState("all");
  const [genre, setGenre] = useState("Tous");
  const [yearB, setYearB] = useState("Tous");
  const [audio, setAudio] = useState("all");
  const [sort, setSort] = useState("rec");
  const [suggests, setSuggests] = useState([]);
  const lastQueryRef = useRef("");
  const inputRef = useRef(null);
  const insets = useSafeAreaInsets();

  useEffect(() => {
    if (active) {
      const t = setTimeout(() => inputRef.current?.focus?.(), 40);
      return () => clearTimeout(t);
    }
    inputRef.current?.blur?.();
  }, [active]);

  const showingSearch = query.trim().length >= 2;

  useEffect(() => {
    const q = query.trim();
    if (q.length < 2) {
      setItems([]);
      setError("");
      return;
    }
    const t = setTimeout(async () => {
      setLoading(true);
      setError("");
      try {
        const data = await searchTitles(q);
        if (lastQueryRef.current !== q) setItems([]);
        lastQueryRef.current = q;
        setItems(data.items || []);
        setPage(1);
        setHasMore((data.items || []).length >= 20);
      } catch (e) {
        setError(e.message);
      } finally {
        setLoading(false);
      }
    }, 350);
    return () => clearTimeout(t);
  }, [query]);

  useEffect(() => {
    const q = query.trim();
    if (q.length < 2) {
      setSuggests([]);
      return;
    }
    const t = setTimeout(async () => {
      try {
        const list = await fetchSuggest(q);
        setSuggests(list.slice(0, 8));
      } catch {
        setSuggests([]);
      }
    }, 250);
    return () => clearTimeout(t);
  }, [query]);

  async function loadMore() {
    const q = query.trim();
    if (q.length < 2 || loading || !hasMore) return;
    setLoading(true);
    try {
      const data = await searchTitles(q, page + 1);
      const more = data.items || [];
      setItems((prev) => {
        const seen = new Set(prev.map((x) => String(x.subjectId)));
        return [...prev, ...more.filter((m) => !seen.has(String(m.subjectId)))];
      });
      setPage((p) => p + 1);
      setHasMore(more.length >= 20);
    } catch {
      setHasMore(false);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (showingSearch) return;
    let live = true;
    const tab =
      type === "series" ? "series" : type === "film" ? "film" : type === "anim" ? "animation" : undefined;
    const params = tab ? { tab } : {};
    const seed = peekCache(`category:${JSON.stringify(params)}`);
    if (seed?.items) {
      setCatItems(seed.items);
      setCatLoading(false);
    } else {
      setCatLoading(true);
    }
    (async () => {
      try {
        const data = await fetchCategory(params);
        if (live) setCatItems(data.items || []);
      } catch {
        if (live && !seed?.items) setCatItems([]);
      } finally {
        if (live) setCatLoading(false);
      }
    })();
    return () => {
      live = false;
    };
  }, [showingSearch, type]);

  const source = showingSearch ? items : catItems;

  const genres = useMemo(() => {
    const count = new Map();
    for (const it of source) {
      for (const g of it.genres || []) count.set(g, (count.get(g) || 0) + 1);
    }
    return [
      "Tous",
      ...Array.from(count.entries())
        .sort((a, b) => b[1] - a[1])
        .slice(0, 10)
        .map(([g]) => g),
    ];
  }, [source]);

  const filtered = useMemo(() => {
    const yTest = YEAR_BUCKETS.find((b) => b.label === yearB)?.test || (() => true);
    let out = source.filter((it) => {
      if (!matchesType(it, type)) return false;
      if (genre !== "Tous" && !(it.genres || []).includes(genre)) return false;
      if (!yTest(it)) return false;
      if (audio === "vf" && !hasVf(it)) return false;
      if (audio === "vo" && hasVf(it)) return false;
      return true;
    });
    if (sort === "rate") out = [...out].sort((a, b) => (b.imdbRating || 0) - (a.imdbRating || 0));
    if (sort === "new") out = [...out].sort((a, b) => (b.year || 0) - (a.year || 0));
    return out;
  }, [source, type, genre, yearB, audio, sort]);

  const featured = showingSearch ? filtered[0] : null;
  const gridItems = featured ? filtered.slice(1) : filtered;
  const filtersOn = type !== "all" || genre !== "Tous" || yearB !== "Tous" || audio !== "all" || sort !== "rec";

  function resetFilters() {
    setType("all");
    setGenre("Tous");
    setYearB("Tous");
    setAudio("all");
    setSort("rec");
  }

  return (
    <View style={[styles.wrap, { paddingTop: insets.top + 6 }]}>
      <View style={[styles.brandRow, { paddingHorizontal: layout.pad }]}>
        <Logo size={layout.isTv ? 26 : 20} />
        {filtersOn ? (
          <Pressable onPress={resetFilters} hitSlop={8}>
            <Text style={styles.reset}>Réinitialiser</Text>
          </Pressable>
        ) : null}
      </View>
      <View style={[styles.searchPad, { marginHorizontal: layout.pad }]}>
        <SearchField inputRef={inputRef} autoFocus={false} value={query} onChangeText={setQuery} />
      </View>

      {showingSearch && suggests.length ? (
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={[styles.suggestRow, { paddingHorizontal: layout.pad }]}
        >
          {suggests.map((s, i) => (
            <Pressable
              key={`${s.subjectId || s.title}-${i}`}
              onPress={() => setQuery(s.title)}
              style={styles.suggestChip}
            >
              <Icon name="search-outline" size={13} color={colors.dim} />
              <Text style={styles.suggestTxt} numberOfLines={1}>
                {s.title}
              </Text>
            </Pressable>
          ))}
        </ScrollView>
      ) : null}

      <ScrollView
        style={{ flex: 1 }}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
        onScroll={({ nativeEvent }) => {
          const { layoutMeasurement, contentOffset, contentSize } = nativeEvent;
          if (layoutMeasurement.height + contentOffset.y >= contentSize.height - 240) {
            loadMore();
          }
        }}
        scrollEventThrottle={200}
        contentContainerStyle={{ paddingBottom: layout.chromeBottom + insets.bottom }}
      >
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={[styles.typeRow, { paddingHorizontal: layout.pad }]}>
          {TYPES.map((t) => (
            <Pressable key={t.id} onPress={() => setType(t.id)} style={styles.typeTab}>
              <Text style={[styles.typeTxt, type === t.id && styles.typeTxtOn]}>{t.label}</Text>
              {type === t.id ? <View style={styles.typeLine} /> : null}
            </Pressable>
          ))}
        </ScrollView>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={[styles.chipRow, { paddingHorizontal: layout.pad }]}>
          {genres.map((g) => (
            <Chip key={g} label={g} on={genre === g} onPress={() => setGenre(g)} />
          ))}
        </ScrollView>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={[styles.chipRow, { paddingHorizontal: layout.pad }]}>
          {YEAR_BUCKETS.map((b) => (
            <Chip key={b.label} label={b.label} on={yearB === b.label} onPress={() => setYearB(b.label)} />
          ))}
        </ScrollView>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={[styles.chipRow, { paddingHorizontal: layout.pad }]}>
          {AUDIO.map((a) => (
            <Chip key={a.id} label={a.label} on={audio === a.id} onPress={() => setAudio(a.id)} />
          ))}
        </ScrollView>

        <View style={[styles.sortRow, { paddingHorizontal: layout.pad }]}>
          {SORTS.map((s) => (
            <Pressable key={s.id} onPress={() => setSort(s.id)} style={styles.sortTab}>
              <Text style={[styles.sortTxt, sort === s.id && styles.sortTxtOn]}>{s.label}</Text>
              {sort === s.id ? <View style={styles.sortLine} /> : null}
            </Pressable>
          ))}
        </View>

        {loading && !source.length ? (
          <ActivityIndicator color={colors.red} style={{ marginTop: 28 }} />
        ) : null}
        {error ? <Text style={styles.err}>{error}</Text> : null}

        {!loading && !filtered.length ? (
          <View style={styles.emptyBox}>
            <View style={styles.emptyRing}>
              <Icon name="search-outline" size={28} color={colors.redSoft} />
            </View>
            <Text style={styles.emptyTitle}>
              {showingSearch ? "Aucun résultat" : "Que veux-tu regarder ?"}
            </Text>
            <Text style={styles.empty}>
              {showingSearch
                ? "Essaie un autre titre, ou élargis les filtres."
                : "Tape au moins 2 lettres, ou parcours avec les filtres."}
            </Text>
          </View>
        ) : null}

        {filtered.length ? (
          <Text style={[styles.count, { paddingHorizontal: layout.pad }]}>
            {filtered.length} résultat{filtered.length > 1 ? "s" : ""}
          </Text>
        ) : null}

        {featured ? (
          <Pressable
            onPress={() => onOpenItem(featured)}
            style={({ pressed }) => [
              styles.featured,
              { height: layout.featuredH, marginHorizontal: layout.pad },
              pressed && { opacity: 0.9 },
            ]}
          >
            <ImageWithFallback
              source={{ uri: featured.cover || featured.coverSmall }}
              style={styles.featuredImg}
              iconSize={36}
            />
            <LinearGradient
              colors={["transparent", "rgba(0,0,0,0.92)"]}
              style={styles.featuredShade}
            />
            {hasVf(featured) ? <VfBadge style={styles.featuredVf} /> : null}
            <View style={styles.featuredBody}>
              <Text numberOfLines={2} style={styles.featuredTitle}>
                {featured.displayTitle || featured.title}
              </Text>
              <View style={styles.featuredMeta}>
                <Text style={styles.featuredSub}>
                  {[featured.typeLabel || (isSeries(featured) ? "Série" : "Film"), featured.year]
                    .filter(Boolean)
                    .join(" · ")}
                </Text>
                <RatingBadge value={featured.imdbRating} />
              </View>
            </View>
          </Pressable>
        ) : null}

        <View style={[styles.grid, { paddingHorizontal: layout.pad, columnGap: layout.gap }]}>
          {gridItems.map((item, i) => (
            <PosterCard
              key={`${item.subjectId}-${i}`}
              item={item}
              width={layout.cellW}
              onPress={() => onOpenItem(item)}
            />
          ))}
        </View>

        {loading && source.length ? (
          <ActivityIndicator color={colors.red} style={{ marginTop: 16 }} />
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  brandRow: {
    paddingHorizontal: 16,
    paddingBottom: 10,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  reset: { color: colors.redSoft, fontWeight: "800", fontSize: 13 },
  searchPad: { marginHorizontal: 16 },
  suggestRow: { gap: 8, marginTop: 10 },
  suggestChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: colors.card,
    borderRadius: 16,
    paddingHorizontal: 12,
    paddingVertical: 7,
    maxWidth: 220,
  },
  suggestTxt: { color: colors.text, fontSize: 13, fontWeight: "600" },
  typeRow: { paddingHorizontal: 16, gap: 20, paddingTop: 14, paddingBottom: 4 },
  typeTab: { alignItems: "center", paddingBottom: 8 },
  typeTxt: { color: colors.dim, fontSize: 15, fontWeight: "700" },
  typeTxtOn: { color: colors.text, fontWeight: "800" },
  typeLine: {
    position: "absolute",
    bottom: 0,
    width: 28,
    height: 2,
    borderRadius: 1,
    backgroundColor: colors.red,
  },
  chipRow: { paddingHorizontal: 16, gap: 8, paddingTop: 10 },
  chip: {
    borderWidth: 1,
    borderColor: "#3A3A3A",
    borderRadius: 16,
    paddingHorizontal: 13,
    paddingVertical: 7,
    backgroundColor: "transparent",
  },
  chipOn: { backgroundColor: colors.red, borderColor: colors.red },
  chipTxt: { color: colors.muted, fontWeight: "700", fontSize: 13 },
  chipTxtOn: { color: "#fff" },
  sortRow: {
    flexDirection: "row",
    gap: 22,
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
  count: {
    color: colors.dim,
    fontSize: 12.5,
    fontWeight: "700",
    paddingHorizontal: 16,
    paddingTop: 14,
    paddingBottom: 4,
  },
  featured: {
    marginHorizontal: 16,
    marginTop: 12,
    height: 168,
    borderRadius: 8,
    overflow: "hidden",
    backgroundColor: "#141414",
  },
  featuredImg: { ...StyleSheet.absoluteFillObject },
  featuredShade: { ...StyleSheet.absoluteFillObject },
  featuredVf: { position: "absolute", top: 10, left: 10 },
  featuredBody: { position: "absolute", left: 14, right: 14, bottom: 12, gap: 4 },
  featuredTitle: { color: "#fff", fontSize: 20, fontWeight: "900", letterSpacing: 0.2 },
  featuredMeta: { flexDirection: "row", alignItems: "center", gap: 8 },
  featuredSub: { color: "rgba(255,255,255,0.8)", fontSize: 13, fontWeight: "600" },
  grid: {
    flexDirection: "row",
    flexWrap: "wrap",
    paddingHorizontal: 16,
    paddingTop: 14,
    columnGap: GAP,
    rowGap: 14,
  },
  emptyBox: { alignItems: "center", marginTop: 48, gap: 8, paddingHorizontal: 28 },
  emptyRing: {
    width: 72,
    height: 72,
    borderRadius: 36,
    borderWidth: 2,
    borderColor: "rgba(229,9,20,0.35)",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 6,
  },
  emptyTitle: { color: colors.text, fontSize: 17, fontWeight: "800" },
  empty: { color: colors.muted, textAlign: "center", lineHeight: 20 },
  err: { color: "#F87171", marginHorizontal: 16, marginTop: 12 },
});
