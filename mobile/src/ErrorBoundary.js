import { Component } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";
import { colors } from "./theme";

export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    // eslint-disable-next-line no-console
    console.error("[CRASH]", error, info);
  }

  render() {
    if (this.state.error) {
      return (
        <View style={styles.root}>
          <Text style={styles.title}>Quelque chose s'est mal passé</Text>
          <Text style={styles.sub}>
            L'application a rencontré une erreur inattendue. Relance l'application
            pour continuer.
          </Text>
          <ScrollView style={styles.log}>
            <Text style={styles.logTxt}>{String(this.state.error?.stack || this.state.error)}</Text>
          </ScrollView>
        </View>
      );
    }
    return this.props.children;
  }
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.bg,
    padding: 24,
    paddingTop: 60,
  },
  title: { color: colors.text, fontSize: 20, fontWeight: "900", marginBottom: 8 },
  sub: { color: colors.dim, fontSize: 14, lineHeight: 20, marginBottom: 16 },
  log: { flex: 1, backgroundColor: "#141414", borderRadius: 10, padding: 12 },
  logTxt: { color: "#FCA5A5", fontSize: 11, fontFamily: "monospace" },
});
