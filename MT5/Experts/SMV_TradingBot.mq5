//+------------------------------------------------------------------+
//|                                             SMV_TradingBot.mq5    |
//|                      Smart Money Vision - Trading Bot (MT5)       |
//|                                                                  |
//|  Strategie :                                                     |
//|   - Une entree est evaluee UNIQUEMENT a la cloture de la bougie  |
//|     de la timeframe selectionnee.                                |
//|   - Direction = sens de la derniere bougie cloturee ALIGNE avec  |
//|     la tendance du marche (filtre moyennes mobiles).             |
//|   - Take Profit = 40 pips (parametrable).                        |
//|   - Grille martingale : si le marche part de +80 pips en sens    |
//|     inverse, une nouvelle position est ouverte avec un lot       |
//|     multiplie par 1.6. Le TP est recalcule au point moyen.       |
//|   - Filtre de News base sur le calendrier economique MT5.        |
//|                                                                  |
//|  AVERTISSEMENT : la martingale/grille comporte un risque eleve   |
//|  de perte totale du capital. A tester en compte DEMO.            |
//+------------------------------------------------------------------+
#property copyright "SMV Trading Bot"
#property version   "1.00"
#property strict

#include <Trade\Trade.mqh>

//====================================================================
//  PARAMETRES
//====================================================================
input group             "=== Money Management ==="
input double   InpInitialLot      = 0.01;      // Lot initial
input double   InpLotMultiplier   = 1.6;       // Multiplicateur de lot (grille)
input int      InpMaxGridLevels   = 10;        // Nombre max de niveaux de grille
input double   InpMaxLot          = 50.0;      // Lot maximum autorise

input group             "=== Distances (en pips) ==="
input double   InpPipSize         = 0.00001;   // Taille d'un pip (defaut demande = 0.00001)
input double   InpTakeProfitPips  = 40;        // Take Profit (pips)
input double   InpGridStepPips     = 80;       // Ecart adverse pour ajouter une position (pips)

input group             "=== Timeframe & Signal ==="
input ENUM_TIMEFRAMES InpTradeTimeframe = PERIOD_CURRENT; // Timeframe de trading
input bool     InpUseTrendFilter  = true;      // Utiliser le filtre de tendance (MM)
input int      InpFastMAPeriod    = 20;        // Periode MM rapide
input int      InpSlowMAPeriod    = 50;        // Periode MM lente
input ENUM_MA_METHOD InpMAMethod  = MODE_EMA;  // Methode MM
input ENUM_APPLIED_PRICE InpMAPrice = PRICE_CLOSE; // Prix applique MM

input group             "=== Filtre de News ==="
input bool     InpUseNewsFilter   = true;      // Activer le filtre de news
input int      InpNewsMinsBefore  = 30;        // Minutes avant la news (pause)
input int      InpNewsMinsAfter   = 30;        // Minutes apres la news (pause)
input int      InpNewsImportance  = 2;         // Importance min (1=faible,2=moyenne,3=forte)

input group             "=== Divers ==="
input long     InpMagic           = 20240716;  // Magic number
input int      InpSlippagePoints  = 30;        // Slippage (points)
input bool     InpCloseAllOnStop  = false;     // Fermer toutes les positions a l'arret

//====================================================================
//  VARIABLES GLOBALES
//====================================================================
CTrade         trade;
int            hFastMA = INVALID_HANDLE;
int            hSlowMA = INVALID_HANDLE;
datetime       g_lastBarTime = 0;
double         g_pip;            // taille d'un pip en prix
double         g_tpPrice;        // distance TP en prix
double         g_gridPrice;      // distance grille en prix

//+------------------------------------------------------------------+
//| Initialisation                                                   |
//+------------------------------------------------------------------+
int OnInit()
{
   trade.SetExpertMagicNumber(InpMagic);
   trade.SetDeviationInPoints(InpSlippagePoints);
   trade.SetTypeFillingBySymbol(_Symbol);
   trade.SetAsyncMode(false);

   g_pip      = InpPipSize;
   g_tpPrice  = InpTakeProfitPips * g_pip;
   g_gridPrice= InpGridStepPips   * g_pip;

   if(InpUseTrendFilter)
   {
      hFastMA = iMA(_Symbol, InpTradeTimeframe, InpFastMAPeriod, 0, InpMAMethod, InpMAPrice);
      hSlowMA = iMA(_Symbol, InpTradeTimeframe, InpSlowMAPeriod, 0, InpMAMethod, InpMAPrice);
      if(hFastMA == INVALID_HANDLE || hSlowMA == INVALID_HANDLE)
      {
         Print("Erreur creation des handles MM");
         return(INIT_FAILED);
      }
   }

   g_lastBarTime = (datetime)SeriesInfoInteger(_Symbol, InpTradeTimeframe, SERIES_LASTBAR_DATE);

   PrintFormat("SMV_TradingBot initialise. Pip=%.5f  TP=%.1f pips  Grille=%.1f pips  Mult=%.2f",
               g_pip, InpTakeProfitPips, InpGridStepPips, InpLotMultiplier);
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| Deinitialisation                                                 |
//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
   if(hFastMA != INVALID_HANDLE) IndicatorRelease(hFastMA);
   if(hSlowMA != INVALID_HANDLE) IndicatorRelease(hSlowMA);
   if(InpCloseAllOnStop) CloseAllPositions();
}

//+------------------------------------------------------------------+
//| Tick                                                             |
//+------------------------------------------------------------------+
void OnTick()
{
   // Gestion continue du TP du panier (recalage si besoin)
   ManageBasketTakeProfit();

   // Detection d'une nouvelle bougie cloturee sur la TF de trading
   datetime barTime = (datetime)SeriesInfoInteger(_Symbol, InpTradeTimeframe, SERIES_LASTBAR_DATE);
   if(barTime == g_lastBarTime)
      return;                          // pas de nouvelle bougie -> on attend
   g_lastBarTime = barTime;

   OnNewBar();                         // la bougie precedente vient de cloturer
}

//+------------------------------------------------------------------+
//| Logique executee a chaque cloture de bougie                      |
//+------------------------------------------------------------------+
void OnNewBar()
{
   int nPos = CountPositions();

   if(nPos == 0)
   {
      // Pas de position : on cherche un signal d'entree initiale
      if(InpUseNewsFilter && IsNewsTime())
      {
         Comment("SMV Bot : entree bloquee (news imminente)");
         return;
      }

      int signal = GetEntrySignal();   // +1 = BUY, -1 = SELL, 0 = rien
      if(signal > 0)
         OpenPosition(ORDER_TYPE_BUY, InpInitialLot);
      else if(signal < 0)
         OpenPosition(ORDER_TYPE_SELL, InpInitialLot);
   }
   else
   {
      // Positions ouvertes : gestion de la grille martingale
      ManageGrid();
   }
}

//+------------------------------------------------------------------+
//| Signal d'entree : sens de la derniere bougie + tendance          |
//+------------------------------------------------------------------+
int GetEntrySignal()
{
   // Derniere bougie CLOTUREE = index 1
   double openPrev  = iOpen(_Symbol,  InpTradeTimeframe, 1);
   double closePrev = iClose(_Symbol, InpTradeTimeframe, 1);
   if(openPrev == 0.0 || closePrev == 0.0)
      return 0;

   int candleDir = 0;
   if(closePrev > openPrev) candleDir = 1;      // bougie haussiere
   else if(closePrev < openPrev) candleDir = -1;// bougie baissiere
   if(candleDir == 0) return 0;

   if(!InpUseTrendFilter)
      return candleDir;

   int trendDir = GetTrendDirection();
   if(trendDir == 0) return 0;

   // On ne trade que si la bougie et la tendance sont alignees
   if(candleDir == trendDir)
      return candleDir;

   return 0;
}

//+------------------------------------------------------------------+
//| Direction de la tendance via croisement MM (bougie cloturee)     |
//+------------------------------------------------------------------+
int GetTrendDirection()
{
   double fast[2], slow[2];
   if(CopyBuffer(hFastMA, 0, 1, 1, fast) < 1) return 0;
   if(CopyBuffer(hSlowMA, 0, 1, 1, slow) < 1) return 0;

   if(fast[0] > slow[0]) return 1;    // haussier
   if(fast[0] < slow[0]) return -1;   // baissier
   return 0;
}

//+------------------------------------------------------------------+
//| Gestion de la grille : ajoute un lot si le marche part de 80 pips|
//| en sens inverse par rapport a la derniere entree.                |
//+------------------------------------------------------------------+
void ManageGrid()
{
   int nPos = CountPositions();
   if(nPos <= 0 || nPos >= InpMaxGridLevels) return;

   long   basketType   = GetBasketType();       // POSITION_TYPE_BUY / SELL
   double lastEntry    = GetLastEntryPrice();
   double lastLot      = GetLastEntryLot();
   if(lastEntry <= 0.0) return;

   double bid = SymbolInfoDouble(_Symbol, SYMBOL_BID);
   double ask = SymbolInfoDouble(_Symbol, SYMBOL_ASK);

   bool addTrade = false;
   if(basketType == POSITION_TYPE_BUY)
   {
      // Le marche a baisse d'au moins la distance grille sous la derniere entree
      if(lastEntry - ask >= g_gridPrice) addTrade = true;
   }
   else if(basketType == POSITION_TYPE_SELL)
   {
      if(bid - lastEntry >= g_gridPrice) addTrade = true;
   }

   if(!addTrade) return;

   // Filtre news egalement pour les ajouts de grille
   if(InpUseNewsFilter && IsNewsTime()) return;

   double nextLot = NormalizeLot(lastLot * InpLotMultiplier);
   if(nextLot > InpMaxLot) nextLot = InpMaxLot;

   if(basketType == POSITION_TYPE_BUY)
      OpenPosition(ORDER_TYPE_BUY, nextLot);
   else
      OpenPosition(ORDER_TYPE_SELL, nextLot);
}

//+------------------------------------------------------------------+
//| Recalcule et applique le TP moyen du panier (+40 pips du prix    |
//| moyen pondere). Ferme automatiquement via le TP du broker.       |
//+------------------------------------------------------------------+
void ManageBasketTakeProfit()
{
   int nPos = CountPositions();
   if(nPos <= 0) return;

   long   basketType = GetBasketType();
   double sumLots = 0.0, sumWeighted = 0.0;

   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(!PositionSelectByTicket(ticket)) continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol) continue;
      if(PositionGetInteger(POSITION_MAGIC) != InpMagic) continue;

      double lot   = PositionGetDouble(POSITION_VOLUME);
      double price = PositionGetDouble(POSITION_PRICE_OPEN);
      sumLots     += lot;
      sumWeighted += lot * price;
   }
   if(sumLots <= 0.0) return;

   double avgPrice = sumWeighted / sumLots;
   double target;
   if(basketType == POSITION_TYPE_BUY)
      target = NormalizeDouble(avgPrice + g_tpPrice, _Digits);
   else
      target = NormalizeDouble(avgPrice - g_tpPrice, _Digits);

   // Appliquer le meme TP a toutes les positions du panier
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(!PositionSelectByTicket(ticket)) continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol) continue;
      if(PositionGetInteger(POSITION_MAGIC) != InpMagic) continue;

      double curTP = PositionGetDouble(POSITION_TP);
      double curSL = PositionGetDouble(POSITION_SL);
      if(MathAbs(curTP - target) > _Point)
         trade.PositionModify(ticket, curSL, target);
   }
}

//====================================================================
//  ORDRES
//====================================================================
void OpenPosition(ENUM_ORDER_TYPE type, double lot)
{
   lot = NormalizeLot(lot);
   if(lot <= 0.0) return;

   double price = (type == ORDER_TYPE_BUY) ? SymbolInfoDouble(_Symbol, SYMBOL_ASK)
                                           : SymbolInfoDouble(_Symbol, SYMBOL_BID);
   double tp;
   if(type == ORDER_TYPE_BUY)  tp = NormalizeDouble(price + g_tpPrice, _Digits);
   else                        tp = NormalizeDouble(price - g_tpPrice, _Digits);

   bool ok;
   if(type == ORDER_TYPE_BUY)
      ok = trade.Buy(lot, _Symbol, 0.0, 0.0, tp, "SMV");
   else
      ok = trade.Sell(lot, _Symbol, 0.0, 0.0, tp, "SMV");

   if(!ok)
      PrintFormat("Echec ouverture %s lot=%.2f : %d %s",
                  EnumToString(type), lot, trade.ResultRetcode(), trade.ResultRetcodeDescription());
}

void CloseAllPositions()
{
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(!PositionSelectByTicket(ticket)) continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol) continue;
      if(PositionGetInteger(POSITION_MAGIC) != InpMagic) continue;
      trade.PositionClose(ticket);
   }
}

//====================================================================
//  UTILITAIRES PANIER
//====================================================================
int CountPositions()
{
   int count = 0;
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(!PositionSelectByTicket(ticket)) continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol) continue;
      if(PositionGetInteger(POSITION_MAGIC) != InpMagic) continue;
      count++;
   }
   return count;
}

long GetBasketType()
{
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(!PositionSelectByTicket(ticket)) continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol) continue;
      if(PositionGetInteger(POSITION_MAGIC) != InpMagic) continue;
      return PositionGetInteger(POSITION_TYPE);
   }
   return -1;
}

double GetLastEntryPrice()
{
   datetime latest = 0;
   double   price  = 0.0;
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(!PositionSelectByTicket(ticket)) continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol) continue;
      if(PositionGetInteger(POSITION_MAGIC) != InpMagic) continue;
      datetime t = (datetime)PositionGetInteger(POSITION_TIME);
      if(t >= latest) { latest = t; price = PositionGetDouble(POSITION_PRICE_OPEN); }
   }
   return price;
}

double GetLastEntryLot()
{
   datetime latest = 0;
   double   lot    = InpInitialLot;
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(!PositionSelectByTicket(ticket)) continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol) continue;
      if(PositionGetInteger(POSITION_MAGIC) != InpMagic) continue;
      datetime t = (datetime)PositionGetInteger(POSITION_TIME);
      if(t >= latest) { latest = t; lot = PositionGetDouble(POSITION_VOLUME); }
   }
   return lot;
}

//+------------------------------------------------------------------+
//| Normalisation du lot selon les contraintes du symbole            |
//+------------------------------------------------------------------+
double NormalizeLot(double lot)
{
   double minLot  = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_MIN);
   double maxLot  = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_MAX);
   double lotStep = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_STEP);
   if(lotStep <= 0.0) lotStep = 0.01;

   lot = MathRound(lot / lotStep) * lotStep;
   if(lot < minLot) lot = minLot;
   if(lot > maxLot) lot = maxLot;
   return NormalizeDouble(lot, 2);
}

//====================================================================
//  FILTRE DE NEWS (calendrier economique MT5)
//====================================================================
bool IsNewsTime()
{
   datetime now = TimeCurrent();
   datetime from = now - InpNewsMinsAfter  * 60;
   datetime to   = now + InpNewsMinsBefore * 60;

   string baseCcy   = SymbolInfoString(_Symbol, SYMBOL_CURRENCY_BASE);
   string profitCcy = SymbolInfoString(_Symbol, SYMBOL_CURRENCY_PROFIT);

   MqlCalendarValue values[];
   int total = CalendarValueHistory(values, from, to, NULL, NULL);
   for(int i = 0; i < total; i++)
   {
      MqlCalendarEvent event;
      if(!CalendarEventById(values[i].event_id, event)) continue;

      // Importance minimale
      int imp = (int)event.importance;   // 0 none,1 low,2 moderate,3 high
      if(imp < InpNewsImportance) continue;

      // Filtre devise (celle du pays de la news doit concerner le symbole)
      MqlCalendarCountry country;
      if(CalendarCountryById(event.country_id, country))
      {
         string ccy = country.currency;
         if(ccy != baseCcy && ccy != profitCcy) continue;
      }

      // Une news pertinente est dans la fenetre -> pause
      return true;
   }
   return false;
}
//+------------------------------------------------------------------+
