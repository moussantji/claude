import Constants from "expo-constants";

// Configure via app.json → expo.extra.apiBase (ou variable d'environnement).
const ENV_API = process.env.API_BASE || Constants.expoConfig?.extra?.apiBase;

export const API_BASE = ENV_API || "https://stream.mandenbaoubab.com/api";

// Place aux réglages produit centralisés.
export const MAX_CONCURRENT_DOWNLOADS = 2;

// Profil d'appareil « MovieBox » pour les requêtes CDN directes.
// Isolé ici pour être auditée/remplacée facilement.
export const MOVIEBOX = {
  UA_MODELS: [
    "23078RKD5C", "SM-A536B", "SM-G991B", "SM-S918B", "Pixel 6", "Pixel 7",
    "CPH2451", "M2101K6G", "22120RN86G", "V2154", "RMX3771", "Infinix X6819",
  ],
  UA_ANDROIDS: ["11", "12", "12", "13", "13", "14"],
  UA_BUILDS: [
    "TQ2A.230405.003", "TP1A.220624.014", "UP1A.231005.007",
    "SQ3A.220705.004", "RP1A.200720.012",
  ],
  UA_LOCALES: ["fr_FR", "en_US", "en_GB", "fr_CA"],
  UA_CRONETS: ["135.0.7012.3", "134.0.6998.2", "133.0.6943.5"],
  UA_TTL_MS: 60 * 60 * 1000,
  UA_FALLBACK:
    "com.community.oneroom/50020045 (Linux; U; Android 13; fr_FR; 23078RKD5C; " +
    "Build/TQ2A.230405.003; Cronet/135.0.7012.3)",
};
