import { Ionicons } from "@expo/vector-icons";
import { useEffect, useState } from "react";
import { Image, Pressable, StyleSheet, Text, TextInput, View } from "react-native";
import { hasVf } from "./api";
import { colors } from "./theme";

export function Icon({ name, size = 22, color = colors.text, style }) {
  return <Ionicons name={name} size={size} color={color} style={style} />;
}

export function toThumbUrl(url, targetW = 240) {
  if (!url || typeof url !== "string") return url;
  // remplace w_480 / w_1200 par une largeur adaptée à la vignette (économie mémoire Fresco)
  if (/w_\d+/.test(url)) return url.replace(/w_\d+/, `w_${targetW}`);
  return url;
}

export function ImageWithFallback({ source, style, resizeMode = "cover", iconSize = 28 }) {
  const [failed, setFailed] = useState(false);
  const uri = source?.uri || "";
  useEffect(() => {
    setFailed(false);
  }, [uri]);
  if (!uri || failed) {
    return (
      <View style={[style, styles.imgFallback]}>
        <Ionicons name="image-outline" size={iconSize} color="#444" />
      </View>
    );
  }
  return (
    <Image
      source={source}
      style={style}
      resizeMode={resizeMode}
      resizeMethod="resize"
      fadeDuration={0}
      onError={() => setFailed(true)}
    />
  );
}

export function Logo({ size = 22 }) {
  return (
    <Text style={[styles.logo, { fontSize: size, lineHeight: size + 4 }]}>
      <Text style={{ color: colors.red }}>M</Text>ANDEN
    </Text>
  );
}

export function RatingBadge({ value, style }) {
  if (value === undefined || value === null || value === "") return null;
  return (
    <View style={[styles.badge, style]}>
      <Icon name="star" size={9} color={colors.gold} />
      <Text style={styles.badgeText}>{value}</Text>
    </View>
  );
}

export function VfBadge({ style }) {
  return (
    <View style={[styles.vf, style]}>
      <Text style={styles.vfTxt}>VF</Text>
    </View>
  );
}

export function SearchField({ value, onChangeText, onFocus, autoFocus, placeholder, inputRef }) {
  return (
    <View style={styles.searchWrap}>
      <Icon name="search" size={18} color="#C8C8C8" style={{ marginRight: 8 }} />
      <TextInput
        ref={inputRef}
        value={value}
        onChangeText={onChangeText}
        onFocus={onFocus}
        autoFocus={autoFocus}
        placeholder={placeholder || "Rechercher films, séries..."}
        placeholderTextColor="#8C8C8C"
        style={styles.search}
        returnKeyType="search"
        clearButtonMode="while-editing"
      />
      {value ? (
        <Pressable onPress={() => onChangeText("")} hitSlop={8}>
          <Icon name="close-circle" size={18} color="#8A8A8A" />
        </Pressable>
      ) : null}
    </View>
  );
}

export function SearchBarButton({ onPress, placeholder, overlay }) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.searchWrap,
        overlay && styles.searchOverlay,
        pressed && { opacity: 0.88 },
      ]}
    >
      <Icon name="search" size={18} color="#C8C8C8" style={{ marginRight: 8 }} />
      <Text style={styles.searchPh}>{placeholder || "Rechercher films, séries..."}</Text>
    </Pressable>
  );
}

export function PlayButton({ label = "Lecture", onPress, style, icon = "play" }) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.play, pressed && styles.pressed, style]}
    >
      <Icon name={icon} size={16} color={colors.playText} />
      <Text style={styles.playTxt}>{label}</Text>
    </Pressable>
  );
}

export function GlassButton({ label, onPress, style, icon }) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.glass, pressed && styles.pressed, style]}
    >
      {icon ? <Icon name={icon} size={16} color={colors.text} /> : null}
      {label ? <Text style={styles.glassTxt}>{label}</Text> : null}
    </Pressable>
  );
}

export function PrimaryButton({ label, icon = "play", onPress, style }) {
  return (
    <PlayButton label={label} icon={icon} onPress={onPress} style={style} />
  );
}

export function GhostButton({ label, onPress, style, icon }) {
  return <GlassButton label={label} icon={icon} onPress={onPress} style={style} />;
}

export function SectionTitle({ children, icon, style, right }) {
  return (
    <View style={[styles.sectionRow, style]}>
      <Text style={styles.section}>{children}</Text>
      {icon ? <Icon name={icon} size={18} color={colors.text} /> : null}
      {right ? <View style={styles.sectionRight}>{right}</View> : null}
    </View>
  );
}

export function Skeleton({ width, height, radius = 6, style }) {
  return (
    <View
      style={[
        { width, height, borderRadius: radius, backgroundColor: "#1C1C1C" },
        style,
      ]}
    />
  );
}

export function PosterSkeleton({ width = 122, showTitle = true }) {
  return (
    <View style={{ width }}>
      <Skeleton width={width} height={Math.round(width * 1.5)} />
      {showTitle ? <Skeleton width={width * 0.78} height={10} style={{ marginTop: 8 }} /> : null}
    </View>
  );
}

export function PosterCard({ item, onPress, width = 128, showTitle = true }) {
  const title = item?.displayTitle || item?.title || "";
  // vignette 2/3 : largeur max 152 (TV) → w_280 suffit (mémoire Fresco /4 vs w_480)
  const thumbW = width <= 100 ? 200 : width <= 128 ? 240 : 280;
  const uri = toThumbUrl(item?.coverSmall || item?.cover, thumbW);
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [{ width }, pressed && { opacity: 0.86 }]}
    >
      <View style={[styles.posterWrap, { width, aspectRatio: 2 / 3 }]}>
        <ImageWithFallback
          source={{ uri }}
          style={styles.posterImg}
          iconSize={22}
        />
        <RatingBadge value={item?.imdbRating} style={styles.posterRating} />
        {hasVf(item) ? <VfBadge style={styles.posterVf} /> : null}
      </View>
      {showTitle ? (
        <Text numberOfLines={2} style={styles.posterTitle}>
          {title}
        </Text>
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  imgFallback: {
    backgroundColor: "#1A1A1A",
    alignItems: "center",
    justifyContent: "center",
  },
  logo: {
    color: colors.text,
    fontWeight: "900",
    letterSpacing: 2.4,
  },
  badge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 3,
    backgroundColor: "rgba(0,0,0,0.72)",
    borderRadius: 4,
    paddingHorizontal: 5,
    paddingVertical: 2,
  },
  badgeText: { color: colors.text, fontSize: 11, fontWeight: "700" },
  vf: {
    backgroundColor: colors.red,
    borderRadius: 3,
    paddingHorizontal: 5,
    paddingVertical: 2,
  },
  vfTxt: { color: "#fff", fontSize: 9, fontWeight: "800", letterSpacing: 0.4 },
  searchWrap: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: colors.search,
    borderRadius: 6,
    height: 42,
    paddingHorizontal: 12,
  },
  searchOverlay: {
    backgroundColor: "rgba(20,20,20,0.45)",
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "rgba(255,255,255,0.16)",
  },
  search: { flex: 1, color: colors.text, fontSize: 16 },
  searchPh: { flex: 1, color: "#8C8C8C", fontSize: 15.5 },
  play: {
    backgroundColor: colors.play,
    borderRadius: 6,
    paddingHorizontal: 20,
    paddingVertical: 11,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
  },
  playTxt: { color: colors.playText, fontWeight: "800", fontSize: 15, letterSpacing: 0.2 },
  glass: {
    backgroundColor: colors.glass,
    borderRadius: 6,
    paddingHorizontal: 18,
    paddingVertical: 11,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
  },
  glassTxt: { color: colors.text, fontWeight: "700", fontSize: 15 },
  pressed: { opacity: 0.84 },
  sectionRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginTop: 22,
    marginHorizontal: 16,
  },
  section: {
    color: colors.text,
    fontSize: 18,
    fontWeight: "700",
    letterSpacing: -0.2,
  },
  sectionRight: { marginLeft: "auto" },
  posterWrap: {
    borderRadius: 6,
    overflow: "hidden",
    backgroundColor: "#1A1A1A",
  },
  posterImg: { width: "100%", height: "100%" },
  posterRating: { position: "absolute", top: 6, right: 6 },
  posterVf: { position: "absolute", top: 6, left: 6 },
  posterTitle: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: "600",
    marginTop: 7,
    lineHeight: 16,
  },
});
