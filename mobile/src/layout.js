import { Platform, useWindowDimensions } from "react-native";

export function computeLayout(width, height) {
  const w = Math.max(1, Number(width) || 360);
  const h = Math.max(1, Number(height) || 640);
  const shortest = Math.min(w, h);
  const longest = Math.max(w, h);
  const landscape = w > h;
  const isTv = Boolean(Platform.isTV) || (shortest >= 700 && longest >= 1100);
  const isTablet = !isTv && shortest >= 600;
  const isPhone = !isTablet && !isTv;
  const wide = w >= 900;
  const sideNav = wide && (isTablet || isTv || landscape);

  const pad = isTv ? 40 : isTablet ? 24 : 16;
  const gap = isTv ? 14 : isTablet ? 10 : 8;
  const target = isTv ? 168 : isTablet ? 150 : 118;
  const cols = Math.max(3, Math.min(8, Math.round((w - pad * 2) / target)));
  const cellW = (w - pad * 2 - gap * (cols - 1)) / cols;
  const posterW = isTv ? 152 : isTablet ? 136 : 122;
  const continueW = isTv ? 240 : isTablet ? 200 : 168;
  const heroH = isPhone
    ? Math.round(Math.min(h * 0.86, 720))
    : isTv
      ? Math.round(Math.min(h * (landscape ? 0.74 : 0.52), 980))
      : Math.round(Math.min(h * (landscape ? 0.72 : 0.5), 820));
  const tabBarH = isTv ? 84 : isTablet ? 70 : 64;
  const titleSize = isTv ? 46 : isTablet ? 38 : 32;
  const railW = isTv ? 228 : 100;
  const fileCols = w >= 1100 ? 4 : w >= 720 ? 3 : 2;
  const castCols = isTv ? 8 : isTablet ? 6 : 4;
  const featuredH = isTv ? 260 : isTablet ? 210 : 168;
  const playerFill = !isPhone && landscape;
  const chromeBottom = sideNav ? 28 : tabBarH + 46;

  return {
    width: w,
    height: h,
    isPhone,
    isTablet,
    isTv,
    landscape,
    wide,
    sideNav,
    pad,
    gap,
    cols,
    cellW,
    posterW,
    continueW,
    heroH,
    tabBarH,
    titleSize,
    railW,
    fileCols,
    recoCols: cols,
    castCols,
    featuredH,
    playerFill,
    chromeBottom,
    hit: isTv ? 14 : 8,
  };
}

export function useLayout() {
  const { width, height } = useWindowDimensions();
  return computeLayout(width, height);
}
