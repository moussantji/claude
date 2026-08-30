// Favoris : stockage LOCAL (fichier) + synchronisation serveur best-effort.
// Fonctionne hors-ligne ; si un compte est connecté, on reflète aussi côté
// API (/favorites) pour retrouver ses favoris sur d'autres appareils.
import { Platform } from "react-native";
import { getAuthToken } from "./api";

let FileSystem = null;
try {
  FileSystem = require("expo-file-system");
} catch {
  FileSystem = null;
}

const NATIVE = Boolean(FileSystem) && Platform.OS !== "web";
const FILE = NATIVE ? `${FileSystem.documentDirectory}favorites.json` : "";
const MAX_ENTRIES = 200;

const listeners = new Set();

function notify() {
  for (const fn of listeners) fn();
}

export function subscribeFavorites(fn) {
  listeners.add(fn);
  return () => listeners.delete(fn);
}

export async function loadFavorites() {
  if (!NATIVE) return [];
  try {
    const info = await FileSystem.getInfoAsync(FILE);
    if (!info.exists) return [];
    const parsed = JSON.parse(await FileSystem.readAsStringAsync(FILE));
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

export async function isFavorite(subjectId) {
  const list = await loadFavorites();
  return list.some((f) => String(f.subjectId) === String(subjectId));
}

async function persist(list) {
  if (!NATIVE) return;
  try {
    await FileSystem.writeAsStringAsync(FILE, JSON.stringify(list.slice(0, MAX_ENTRIES)));
  } catch {
    /* ignore */
  }
}

function normalize(item) {
  return {
    subjectId: item.subjectId,
    title: item.displayTitle || item.title,
    displayTitle: item.displayTitle || item.title,
    cover: item.cover,
    coverSmall: item.coverSmall || item.cover,
    year: item.year,
    updatedAt: Date.now(),
  };
}

/**
 * Bascule un favori. Retourne le nouvel état { favorite, list }.
 * Tente aussi la synchro serveur (ignorée en silence si hors-ligne / non connecté).
 */
export async function toggleFavorite(item) {
  const list = await loadFavorites();
  const key = String(item.subjectId);
  const exists = list.some((f) => String(f.subjectId) === key);
  let next;
  if (exists) {
    next = list.filter((f) => String(f.subjectId) !== key);
  } else {
    next = [normalize(item), ...list];
  }
  await persist(next);
  notify();

  if (getAuthToken()) {
    import("./api")
      .then(({ addFavorite, removeFavorite }) =>
        exists ? removeFavorite(key) : addFavorite(key)
      )
      .catch(() => {});
  }
  return { favorite: !exists, list: next };
}
