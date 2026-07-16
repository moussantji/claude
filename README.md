# SMV Trading Bot — Smart Money Vision (MT4 & MT5)

Bot de trading automatique pour **MetaTrader 4** et **MetaTrader 5**, accompagné d'un
indicateur visuel **Smart Money Vision (SMV)**.

> ⚠️ **AVERTISSEMENT RISQUE** — Ce bot utilise une **grille martingale** (multiplication
> du lot à chaque position perdante). Cette approche peut générer des gains réguliers…
> jusqu'à un mouvement adverse prolongé qui peut **vider entièrement le compte**.
> Testez **obligatoirement en compte DÉMO** avant tout usage réel. Ceci n'est pas un
> conseil en investissement.

---

## 1. Contenu du dépôt

```
MT5/
  Experts/SMV_TradingBot.mq5     -> Robot de trading (logique + ordres + filtre news)
  Indicators/SMV_Vision.mq5      -> Indicateur visuel (thème, zones, OB, liquidités, panneau)
MT4/
  Experts/SMV_TradingBot.mq4     -> Robot de trading (version MQL4)
  Indicators/SMV_Vision.mq4      -> Indicateur visuel (version MQL4)
```

- L'**Expert Advisor (EA)** prend les trades.
- L'**Indicateur** gère uniquement l'affichage (analyse SMV, zones, gains jour/veille, news).
- Les deux se posent **ensemble sur le même graphique**.

---

## 2. La stratégie de trading

| Règle | Comportement |
|-------|--------------|
| **Moment d'entrée** | Uniquement **à la clôture de la bougie** de la timeframe du graphique. Le bot attend la fermeture de la dernière bougie avant d'agir. |
| **Direction** | Sens de la **dernière bougie clôturée** (haussière → achat, baissière → vente) **aligné avec la tendance** du marché (croisement MM rapide/lente). |
| **Take Profit** | **40 pips** (paramétrable). |
| **Grille / renfort** | Si le marché repart de **+80 pips en sens inverse**, une nouvelle position est ouverte dans le **même sens** avec un lot **× 1,6**. |
| **TP recalculé** | À chaque renfort, le TP de tout le panier est replacé à **+40 pips du prix moyen pondéré**, pour clôturer l'ensemble en profit. |
| **Pip** | Par défaut `1 pip = 0.00001` (comme demandé). Modifiable via le paramètre `InpPipSize`. |
| **News** | Le bot **suspend les entrées** autour des annonces économiques. |

### Définition du pip
Le paramètre `InpPipSize = 0.00001` suit votre demande. Sur la plupart des paires
Forex 5 chiffres, cela correspond au **point** (5ᵉ décimale). Donc `40 pips = 0.00040`
et `80 pips = 0.00080`. Si vous préférez le pip « classique » (4ᵉ décimale), mettez
`InpPipSize = 0.0001`.

---

## 3. L'indicateur Smart Money Vision (SMV)

- 🎨 **Thème** : fond blanc sale, bougies **haussières bleues**, **baissières noires**.
- 🟦 **Buy zones** (bleu clair transparent) = Order Blocks haussiers.
- 🟥 **Sell zones** (rouge clair transparent) = Order Blocks baissiers.
- 〰️ **Liquidités** : `BSL` (buy-side) sur les sommets, `SSL` (sell-side) sur les creux.
- 🧱 **Order Blocks** : dernière bougie opposée avant une impulsion cassant sa mèche.
- 📊 **Panneau d'analyse** : biais (haussier/baissier), structure (BOS HH/HL ou LH/LL).
- 💰 **Gains du jour et de la veille** affichés en temps réel.
- 📰 **Alerte News** (voir la note MT4 ci-dessous).

*Note : la transparence des zones est obtenue en dessinant des rectangles remplis
placés en arrière-plan (les bougies passent par-dessus), ce qui donne l'effet voulu.
MetaTrader ne gère pas de vrai canal alpha pour les rectangles.*

---

## 4. Filtre & alerte de News

- **MT5** : utilise le **calendrier économique natif** de MetaTrader 5. Filtrage
  automatique par devise du symbole et par niveau d'importance.
- **MT4** : MetaTrader 4 **n'a pas de calendrier intégré**. Le filtre repose sur une
  **liste horaire manuelle** (`InpNewsTimes`, ex. `"13:30,15:00"`, heure du serveur).
  Renseignez-y les heures des annonces importantes de la journée.

---

## 5. Installation

### MetaTrader 5
1. Dans le terminal : `Fichier > Ouvrir le dossier de données`.
2. Copier `SMV_TradingBot.mq5` dans `MQL5/Experts/`.
3. Copier `SMV_Vision.mq5` dans `MQL5/Indicators/`.
4. Redémarrer MT5 ou faire `Actualiser` dans le Navigateur, puis compiler (F7 dans MetaEditor).
5. Pour le filtre news : `Outils > Options > Communauté/Serveur` — autoriser le calendrier.

### MetaTrader 4
1. `Fichier > Ouvrir le dossier de données`.
2. Copier `SMV_TradingBot.mq4` dans `MQL4/Experts/`.
3. Copier `SMV_Vision.mq4` dans `MQL4/Indicators/`.
4. Compiler dans MetaEditor (F7).

### Mise en route
1. Ouvrir le graphique du symbole, choisir la **timeframe** = celle sur laquelle vous
   voulez trader (le bot suit la timeframe du graphique).
2. Glisser **l'indicateur** `SMV_Vision` sur le graphique.
3. Glisser **l'EA** `SMV_TradingBot` sur le graphique.
4. Activer **AutoTrading** (bouton en haut).
5. Vérifier les paramètres (lot, TP, grille, magic number).

---

## 6. Et sur mobile ?

**Ce n'est techniquement pas possible sur les applications MetaTrader mobiles.**
Les apps **MT4/MT5 pour Android et iOS ne permettent pas d'exécuter des Expert Advisors
ni des indicateurs personnalisés** — c'est une limitation de MetaQuotes, pas du code.

Solutions pour « trader depuis le mobile » :
1. **VPS Forex** (recommandé) : installez MT4/MT5 + le bot sur un VPS qui tourne 24/7.
   Le bot trade tout seul sur le VPS, et vous **suivez / contrôlez** le compte depuis
   l'app mobile (les trades ouverts par l'EA y sont visibles).
2. **PC allumé en permanence** : même principe, le bot tourne sur votre PC.

> Une vraie app mobile native reproduisant cette stratégie nécessiterait un projet
> séparé (connexion à l'API du broker, ex. MetaApi/cTrader Open API). Dites-moi si
> vous voulez que je parte sur cette voie.

---

## 7. Principaux paramètres (EA)

| Paramètre | Défaut | Rôle |
|-----------|--------|------|
| `InpInitialLot` | 0.01 | Lot de la première position |
| `InpLotMultiplier` | 1.6 | Multiplicateur de lot de la grille |
| `InpMaxGridLevels` | 10 | Nombre max de positions empilées |
| `InpPipSize` | 0.00001 | Valeur d'un pip |
| `InpTakeProfitPips` | 40 | Take Profit en pips |
| `InpGridStepPips` | 80 | Écart adverse déclenchant un renfort |
| `InpUseTrendFilter` | true | Aligner l'entrée sur la tendance MM |
| `InpFastMAPeriod` / `InpSlowMAPeriod` | 20 / 50 | Périodes des moyennes mobiles |
| `InpUseNewsFilter` | true (MT5) | Pause autour des news |
| `InpMagic` | 20240716 | Identifiant des ordres du bot |

---

## 8. Limites connues / bonnes pratiques
- La martingale doit être surveillée : définissez un **capital max de perte** et surveillez `InpMaxGridLevels`.
- Testez d'abord dans le **Strategy Tester** puis en **DÉMO** plusieurs semaines.
- La détection Order Block / liquidité est une approximation des concepts Smart Money,
  pas une vérité absolue de marché.
