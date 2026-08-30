import { LinearGradient } from "expo-linear-gradient";
import { useEffect, useMemo, useState } from "react";
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import {
  fetchFavorites,
  fetchHistory,
  fetchMe,
  login,
  logout as apiLogout,
  register as apiRegister,
} from "../api";
import { useLayout } from "../layout";
import { colors } from "../theme";
import { loadFavorites, subscribeFavorites } from "../favorites";
import { useJobs } from "../useJobs";
import { Icon, PosterCard } from "../ui";

function Stat({ label, value }) {
  return (
    <View style={styles.stat}>
      <Text style={styles.statVal}>{value}</Text>
      <Text style={styles.statLbl}>{label}</Text>
    </View>
  );
}

export default function ProfileScreen({ onOpenItem, onOpenFiles }) {
  const [me, setMe] = useState(null);
  const [history, setHistory] = useState([]);
  const [showForm, setShowForm] = useState(false);
  const [mode, setMode] = useState("login");
  const [email, setEmail] = useState("");
  const [name, setName] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [favs, setFavs] = useState([]);
  const jobs = useJobs();
  const insets = useSafeAreaInsets();
  const layout = useLayout();

  async function load() {
    setBusy(true);
    try {
      const user = await fetchMe();
      setMe(user.user || user);
      const hist = await fetchHistory().catch((e) => {
        if (e.code === "AUTH") return { items: [] };
        throw e;
      });
      setHistory(hist.items || hist.history || []);
      setError("");
    } catch (e) {
      setMe(null);
      setError(e.code === "AUTH" ? "" : e.message);
    } finally {
      setBusy(false);
    }
  }

  useEffect(() => {
    load();
    const unsub = subscribeFavorites(() => refreshFavs());
    refreshFavs();
    return unsub;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function refreshFavs() {
    const list = await loadFavorites();
    setFavs(Array.isArray(list) ? list : []);
    if (me) {
      fetchFavorites()
        .then((srv) => Array.isArray(srv) && srv.length && setFavs(srv))
        .catch(() => {});
    }
  }

  async function onAuth() {
    setError("");
    if (!email.trim() || !password) {
      setError("Email et mot de passe requis");
      return;
    }
    if (mode === "register" && !name.trim()) {
      setError("Indique un nom pour créer le compte");
      return;
    }
    setBusy(true);
    try {
      if (mode === "register") {
        await apiRegister(email.trim(), password, name.trim());
      } else {
        await login(email.trim(), password);
      }
      setEmail("");
      setPassword("");
      setName("");
      setShowForm(false);
      await load();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  }

  async function onLogout() {
    await apiLogout();
    setMe(null);
    setHistory([]);
  }

  const accountName = me?.name || me?.email || "Invité";
  const userId = me?.id || me?.subjectId || "—";
  const doneCount = useMemo(() => jobs.filter((j) => j.status === "done").length, [jobs]);

  return (
    <View style={styles.wrap}>
      <ScrollView
        contentContainerStyle={{
          paddingBottom: layout.chromeBottom + insets.bottom,
          paddingHorizontal: layout.isPhone ? 0 : 8,
          maxWidth: layout.isTv ? 980 : undefined,
          width: "100%",
          alignSelf: "center",
        }}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.hero}>
          <LinearGradient
            colors={["#1A0507", "#0A0000", colors.bg]}
            locations={[0, 0.55, 1]}
            style={StyleSheet.absoluteFill}
          />
          <View style={{ paddingTop: insets.top + 10, paddingHorizontal: 20, paddingBottom: 8 }}>
            <Text style={styles.pageTitle}>Compte</Text>
          </View>
          <View
            style={{
              paddingTop: 10,
              paddingHorizontal: 20,
              flexDirection: "row",
              alignItems: "center",
              gap: 16,
            }}
          >
            <View style={styles.avatar}>
              {me ? (
                <Text style={styles.letter}>{String(accountName).charAt(0).toUpperCase()}</Text>
              ) : (
                <Icon name="person" size={36} color={colors.onRed} />
              )}
            </View>
            <View style={{ flex: 1 }}>
              <Text numberOfLines={1} style={styles.name}>
                {accountName}
              </Text>
              <Text style={styles.idTxt}>ID Manden · {String(userId)}</Text>
            </View>
          </View>

          {me ? (
            <Pressable onPress={onLogout} style={styles.loginRow} hitSlop={6}>
              <Icon name="log-out-outline" size={18} color={colors.onRed} />
              <Text style={styles.loginTxt}>Déconnexion</Text>
            </Pressable>
          ) : (
            <Pressable onPress={() => setShowForm((v) => !v)} style={styles.loginRow}>
              <Text style={styles.loginTxt}>Connexion</Text>
              <Icon name={showForm ? "chevron-up" : "chevron-forward"} size={18} color={colors.onRed} />
            </Pressable>
          )}

          <View style={styles.statsRow}>
            <Stat label="Vus" value={history.length} />
            <View style={styles.statDiv} />
            <Stat label="Téléchargements" value={jobs.length} />
            <View style={styles.statDiv} />
            <Stat label="Hors-ligne" value={doneCount} />
          </View>
        </View>

        {!me && showForm ? (
          <View style={styles.form}>
            <View style={styles.inputWrap}>
              <Icon name="mail-outline" size={18} color={colors.dim} />
              <TextInput
                value={email}
                onChangeText={setEmail}
                autoCapitalize="none"
                keyboardType="email-address"
                placeholder="Email"
                placeholderTextColor="#8A8A8A"
                style={styles.input}
              />
            </View>
            {mode === "register" ? (
              <View style={styles.inputWrap}>
                <Icon name="person-outline" size={18} color={colors.dim} />
                <TextInput
                  value={name}
                  onChangeText={setName}
                  placeholder="Nom"
                  placeholderTextColor="#8A8A8A"
                  style={styles.input}
                />
              </View>
            ) : null}
            <View style={styles.inputWrap}>
              <Icon name="lock-closed-outline" size={18} color={colors.dim} />
              <TextInput
                value={password}
                onChangeText={setPassword}
                secureTextEntry
                placeholder="Mot de passe"
                placeholderTextColor="#8A8A8A"
                style={styles.input}
              />
            </View>
            <Pressable style={[styles.btn, busy && { opacity: 0.7 }]} onPress={onAuth}>
              <Icon name={mode === "register" ? "person-add-outline" : "log-in-outline"} size={18} color={colors.onRed} />
              <Text style={styles.btnText}>
                {busy ? "…" : mode === "register" ? "Créer le compte" : "Connexion"}
              </Text>
            </Pressable>
            <Pressable
              onPress={() => setMode((m) => (m === "register" ? "login" : "register"))}
              hitSlop={8}
              style={styles.switchRow}
            >
              <Text style={styles.switchTxt}>
                {mode === "register"
                  ? "Déjà un compte ? Se connecter"
                  : "Pas de compte ? Créer un compte"}
              </Text>
            </Pressable>
            {mode === "login" ? (
              <Text style={styles.hint}>Mot de passe oublié ? Contacte un administrateur.</Text>
            ) : null}
          </View>
        ) : null}

        {error ? <Text style={styles.error}>{error}</Text> : null}

        <View style={styles.menu}>
          <Pressable style={styles.filesRow} onPress={() => onOpenFiles?.()}>
            <View style={styles.menuIcon}>
              <Icon name="download-outline" size={18} color={colors.redSoft} />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.filesTxt}>Mes fichiers</Text>
              <Text style={styles.filesSub}>
                {doneCount} hors-ligne · {jobs.length} au total
              </Text>
            </View>
            <Icon name="chevron-forward" size={16} color={colors.dim} />
          </Pressable>
        </View>

        {favs.length ? (
          <>
            <Text style={[styles.h2, { paddingHorizontal: layout.pad }]}>Mes favoris</Text>
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={[styles.histRow, { paddingHorizontal: layout.pad }]}
            >
              {favs.slice(0, 20).map((item, idx) => (
                <PosterCard
                  key={`${item.subjectId}-${idx}`}
                  item={item}
                  width={layout.posterW}
                  onPress={() => onOpenItem?.(item)}
                />
              ))}
            </ScrollView>
          </>
        ) : null}

        {history.length ? (
          <>
            <Text style={[styles.h2, { paddingHorizontal: layout.pad }]}>Continuer à regarder</Text>
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={[styles.histRow, { paddingHorizontal: layout.pad }]}
            >
              {history.slice(0, 12).map((item, idx) => (
                <PosterCard
                  key={`${item.subjectId}-${idx}`}
                  item={item}
                  width={layout.posterW}
                  onPress={() => onOpenItem?.(item)}
                />
              ))}
            </ScrollView>
          </>
        ) : (
          <View style={styles.emptyBox}>
            <View style={styles.emptyRing}>
              <Icon name="film-outline" size={28} color={colors.dim} />
            </View>
            <Text style={styles.emptyTitle}>Pas encore de contenu</Text>
            <Text style={styles.empty}>Tes films et séries regardés apparaîtront ici.</Text>
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  hero: { paddingBottom: 8 },
  pageTitle: { color: colors.text, fontSize: 22, fontWeight: "800", letterSpacing: -0.4 },
  avatar: {
    width: 76,
    height: 76,
    borderRadius: 38,
    backgroundColor: colors.red,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 3,
    borderColor: "rgba(255,255,255,0.18)",
  },
  letter: { fontSize: 30, fontWeight: "800", color: colors.onRed },
  name: { color: colors.text, fontSize: 22, fontWeight: "900", letterSpacing: -0.4 },
  idTxt: { color: "rgba(255,255,255,0.65)", fontSize: 12.5, marginTop: 4 },
  loginRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginHorizontal: 20,
    marginTop: 22,
    backgroundColor: colors.red,
    borderRadius: 14,
    paddingHorizontal: 16,
    paddingVertical: 14,
  },
  loginTxt: { color: colors.onRed, fontSize: 16, fontWeight: "900" },
  statsRow: { flexDirection: "row", marginTop: 22, paddingHorizontal: 12, alignItems: "center" },
  stat: { flex: 1, alignItems: "center", gap: 2 },
  statVal: { color: colors.text, fontSize: 21, fontWeight: "900" },
  statLbl: { color: "rgba(255,255,255,0.6)", fontSize: 12, fontWeight: "600" },
  statDiv: { width: 1, height: 28, backgroundColor: "rgba(255,255,255,0.12)" },
  form: { marginTop: 18, gap: 10, paddingHorizontal: 20 },
  inputWrap: {
    backgroundColor: colors.card,
    borderRadius: 12,
    paddingHorizontal: 14,
    height: 46,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  input: { flex: 1, color: colors.text, height: 46 },
  btn: {
    backgroundColor: colors.red,
    borderRadius: 12,
    height: 46,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
  },
  btnText: { color: colors.onRed, fontWeight: "800" },
  switchRow: { marginTop: 4, alignItems: "center", paddingVertical: 6 },
  switchTxt: { color: colors.redSoft, fontWeight: "800", fontSize: 13.5 },
  hint: { color: colors.dim, fontSize: 12, textAlign: "center", marginTop: 2 },
  error: { color: "#F87171", marginTop: 12, textAlign: "center", paddingHorizontal: 20 },
  menu: { marginTop: 8 },
  filesRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    marginHorizontal: 20,
    marginTop: 16,
    backgroundColor: colors.card,
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 14,
  },
  menuIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: "rgba(229,9,20,0.16)",
    alignItems: "center",
    justifyContent: "center",
  },
  filesTxt: { color: colors.text, fontWeight: "800", fontSize: 15 },
  filesSub: { color: colors.dim, fontSize: 12, marginTop: 2 },
  h2: {
    color: colors.text,
    fontWeight: "800",
    fontSize: 18,
    marginTop: 26,
    paddingHorizontal: 16,
    letterSpacing: -0.3,
  },
  histRow: { paddingHorizontal: 16, gap: 10, paddingTop: 12 },
  emptyBox: { alignItems: "center", marginTop: 48, paddingHorizontal: 32, gap: 8 },
  emptyRing: {
    width: 72,
    height: 72,
    borderRadius: 36,
    borderWidth: 2,
    borderColor: "rgba(255,255,255,0.1)",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  emptyTitle: { color: colors.text, fontSize: 16, fontWeight: "800" },
  empty: { color: colors.dim, textAlign: "center", lineHeight: 20 },
});
