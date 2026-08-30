import { NavigationContainer } from "@react-navigation/native";
import { createNativeStackNavigator } from "@react-navigation/native-stack";
import { StatusBar } from "expo-status-bar";
import { useEffect, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useRoute } from "@react-navigation/native";
import DetailScreen from "./src/screens/DetailScreen";
import FilesScreen from "./src/screens/DownloadsScreen";
import HomeScreen from "./src/screens/HomeScreen";
import PlayerScreen from "./src/screens/PlayerScreen";
import ProfileScreen from "./src/screens/ProfileScreen";
import SearchScreen from "./src/screens/SearchScreen";
import { initAuth, isSeries } from "./src/api";
import { initDownloads, startDownload } from "./src/downloadManager";
import { useLayout } from "./src/layout";
import { colors } from "./src/theme";
import { Icon, Logo } from "./src/ui";
import ErrorBoundary from "./src/ErrorBoundary";

const Stack = createNativeStackNavigator();

const TABS = [
  { id: "home", label: "Accueil", icon: "home-outline", iconOn: "home" },
  { id: "search", label: "Recherche", icon: "search-outline", iconOn: "search" },
  { id: "downloads", label: "Téléchargements", icon: "download-outline", iconOn: "download" },
  { id: "profile", label: "Compte", icon: "person-outline", iconOn: "person" },
];

function Tabs({ navigation }) {
  const [tab, setTab] = useState("home");
  const [detailItem, setDetailItem] = useState(null);
  const insets = useSafeAreaInsets();
  const layout = useLayout();

  function openItem(item) {
    setDetailItem(item);
  }

  // Lecture : une série passe toujours par sa fiche (choix saison/épisode),
  // un film joue directement.
  function playItem(item) {
    if (isSeries(item)) {
      setDetailItem(item);
      return;
    }
    navigation.navigate("Player", { item });
  }

  // Téléchargement en fond : reste sur l'écran courant.
  function addDownload(item) {
    startDownload(item).catch(() => {});
  }

  // Lecture depuis la fiche : choisit le lecteur plein écran.
  function playDetail(payload, queue) {
    navigation.navigate("Player", { item: payload, queue });
  }

  const navItems = TABS.map((item) => {
    const active = tab === item.id;
    return (
      <Pressable
        key={item.id}
        onPress={() => {
          setDetailItem(null);
          setTab(item.id);
        }}
        style={({ pressed }) => [
          layout.sideNav ? styles.railItem : styles.tab,
          layout.sideNav && active && styles.railItemOn,
          pressed && { opacity: 0.75 },
        ]}
      >
        <Icon
          name={active ? item.iconOn : item.icon}
          size={layout.isTv ? 26 : 22}
          color={active ? colors.redSoft : colors.dim}
        />
        <Text
          style={[layout.sideNav ? styles.railLabel : styles.tabLabel, active && styles.active]}
          numberOfLines={1}
          adjustsFontSizeToFit
        >
          {item.label}
        </Text>
      </Pressable>
    );
  });

  const homeActive = tab === "home" && !detailItem;

  return (
    <View style={[styles.root, layout.sideNav && styles.rootRow]}>
      {layout.sideNav ? (
        <View
          style={[
            styles.rail,
            {
              width: layout.railW,
              paddingTop: insets.top + 18,
              paddingBottom: Math.max(16, insets.bottom),
            },
          ]}
        >
          <Logo size={layout.isTv ? 22 : 16} />
          <View style={styles.railList}>{navItems}</View>
        </View>
      ) : null}
      <View style={styles.screen}>
        <View
          style={[styles.pane, homeActive ? null : styles.paneOff]}
          pointerEvents={homeActive ? "auto" : "none"}
        >
          <HomeScreen
            active={homeActive}
            onOpenItem={openItem}
            onAddDownload={addDownload}
            onPlay={playItem}
            onOpenFiles={() => setTab("downloads")}
            onOpenSearch={() => setTab("search")}
          />
        </View>
        <View
          style={[styles.pane, tab !== "search" && styles.paneOff]}
          pointerEvents={tab === "search" ? "auto" : "none"}
        >
          <SearchScreen active={tab === "search"} onOpenItem={openItem} />
        </View>
        <View
          style={[styles.pane, tab !== "downloads" && styles.paneOff]}
          pointerEvents={tab === "downloads" ? "auto" : "none"}
        >
          <FilesScreen onPlay={playItem} onOpenItem={openItem} />
        </View>
        <View
          style={[styles.pane, tab !== "profile" && styles.paneOff]}
          pointerEvents={tab === "profile" ? "auto" : "none"}
        >
          <ProfileScreen onOpenItem={openItem} onOpenFiles={() => setTab("downloads")} />
        </View>

        {/* fiche affichée en overlay : la barre basse reste visible */}
        {detailItem ? (
          <View style={[styles.pane, styles.detailPane]}>
            <DetailScreen
              item={detailItem}
              onBack={() => setDetailItem(null)}
              onAddDownload={addDownload}
              onOpenItem={setDetailItem}
              onPlay={playDetail}
            />
          </View>
        ) : null}
      </View>

      {!layout.sideNav ? (
        <View
          style={[
            styles.tabBar,
            {
              paddingBottom: Math.max(8, insets.bottom),
              height: layout.tabBarH + insets.bottom,
            },
          ]}
        >
          {navItems}
        </View>
      ) : null}
    </View>
  );
}

function DetailScreenWrapper({ navigation }) {
  const params = useRoute().params || {};
  if (!params.item) {
    // deep link sans payload : on ne peut rien afficher
    return (
      <View style={[styles.root, { justifyContent: "center", alignItems: "center", padding: 24 }]}>
        <Text style={{ color: colors.text, fontSize: 16, fontWeight: "800", marginBottom: 8 }}>
          Titre introuvable
        </Text>
        <Text style={{ color: colors.dim, textAlign: "center" }}>
          Ce lien ne pointe vers aucun titre. Revenez à l'accueil.
        </Text>
        <Pressable onPress={() => navigation.goBack()} style={{ marginTop: 16 }}>
          <Text style={{ color: colors.redSoft, fontWeight: "800" }}>Retour</Text>
        </Pressable>
      </View>
    );
  }
  return (
    <DetailScreen
      item={params.item}
      onBack={() => navigation.goBack()}
      // reco → push sur le stack : Retour remonte l'historique complet
      onOpenItem={(rec) => navigation.push("Detail", { item: rec })}
      onPlay={(payload, queue) =>
        isSeries(payload)
          ? navigation.navigate("Player", { item: payload, queue })
          : navigation.navigate("Player", { item: payload })
      }
      onAddDownload={(payload) => startDownload(payload).catch(() => {})}
    />
  );
}

function PlayerScreenWrapper({ navigation }) {
  const params = useRoute().params || {};
  const { item, queue } = params;
  if (!item) {
    return (
      <View style={[styles.root, { justifyContent: "center", alignItems: "center", padding: 24 }]}>
        <Text style={{ color: colors.text, fontSize: 16, fontWeight: "800", marginBottom: 8 }}>
          Lecture impossible
        </Text>
        <Text style={{ color: colors.dim, textAlign: "center" }}>
          Ce lien ne contient pas de vidéo. Ouvre un titre depuis l'application.
        </Text>
        <Pressable onPress={() => navigation.goBack()} style={{ marginTop: 16 }}>
          <Text style={{ color: colors.redSoft, fontWeight: "800" }}>Retour</Text>
        </Pressable>
      </View>
    );
  }

  function goNext() {
    if (!queue?.length) {
      navigation.goBack();
      return;
    }
    const idx = queue.findIndex(
      (e) => e.season === item.season && e.episode === item.episode
    );
    const next = queue[idx + 1];
    if (!next) {
      navigation.goBack();
      return;
    }
    navigation.replace("Player", {
      item: { ...item, season: next.season, episode: next.episode },
      queue,
    });
  }

  return <PlayerScreen item={item} queue={queue} onBack={() => navigation.goBack()} onNext={goNext} />;
}

export default function App() {
  useEffect(() => {
    initAuth();
    initDownloads();
  }, []);

  return (
    <ErrorBoundary>
      <NavigationContainer
        linking={{
          prefixes: ["mandenstream://"],
          config: {
            screens: {
              Tabs: "",
              Detail: "detail/:subjectId",
              Player: "play",
            },
          },
        }}
      >
        <StatusBar style="light" />
        <Stack.Navigator screenOptions={{ headerShown: false }}>
          <Stack.Screen name="Tabs" component={Tabs} />
          <Stack.Screen name="Detail" component={DetailScreenWrapper} />
          <Stack.Screen name="Player" component={PlayerScreenWrapper} />
        </Stack.Navigator>
      </NavigationContainer>
    </ErrorBoundary>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.bg },
  rootRow: { flexDirection: "row" },
  screen: { flex: 1 },
  pane: { ...StyleSheet.absoluteFillObject, zIndex: 1 },
  paneOff: { opacity: 0, zIndex: 0 },
  detailPane: { zIndex: 5, backgroundColor: colors.bg },
  tabBar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    zIndex: 10,
    elevation: 10,
    backgroundColor: "rgba(8,8,8,0.96)",
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "rgba(255,255,255,0.08)",
    flexDirection: "row",
    paddingTop: 8,
  },
  tab: { flex: 1, alignItems: "center", gap: 3 },
  tabLabel: { color: colors.dim, fontSize: 10.5, fontWeight: "600" },
  active: { color: colors.redSoft, fontWeight: "800" },
  rail: {
    backgroundColor: "rgba(8,8,8,0.98)",
    borderRightWidth: StyleSheet.hairlineWidth,
    borderRightColor: "rgba(255,255,255,0.08)",
    paddingHorizontal: 10,
    zIndex: 10,
    elevation: 10,
  },
  railList: { marginTop: 28, gap: 8, flex: 1 },
  railItem: {
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    paddingVertical: 14,
    borderRadius: 10,
  },
  railItemOn: { backgroundColor: "rgba(229,9,20,0.14)" },
  railLabel: { color: colors.dim, fontSize: 11, fontWeight: "700", textAlign: "center" },
});
