// Historique de visionnage LOCAL (aucun compte requis).
// Stocké dans documentDirectory/watch_history.json, une entrée par
// titre/épisode, triée par activité récente, plafonnée à 60 entrées.
import { Platform } from "react-native";

let FileSystem = null;
try {
  FileSystem = require("expo-file-system");
} catch {
  FileSystem = null;
}

const NATIVE = Boolean(FileSystem) && Platform.OS !== "web";
const FILE = NATIVE ? `${FileSystem.documentDirectory}watch_history.json` : "";
const MAX_ENTRIES = 60;

export async function loadWatchHistory() {
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

/** Insère ou met à jour une entrée (clé subjectId+season+episode). */
export async function saveWatchEntry(entry) {
  if (!NATIVE || !entry?.subjectId) return [];
  const list = await loadWatchHistory();
  const key = `${entry.subjectId}|${entry.season || 0}|${entry.episode || 0}`;
  const next = {
    ...entry,
    season: entry.season || 0,
    episode: entry.episode || 0,
    updatedAt: Date.now(),
  };
  const filtered = list.filter(
    (e) => `${e.subjectId}|${e.season || 0}|${e.episode || 0}` !== key
  );
  const out = [next, ...filtered].slice(0, MAX_ENTRIES);
  try {
    await FileSystem.writeAsStringAsync(FILE, JSON.stringify(out));
  } catch {
    /* ignore */
  }
  return out;
}
