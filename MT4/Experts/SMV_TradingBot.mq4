//+------------------------------------------------------------------+
//|                                             SMV_TradingBot.mq4    |
//|                      Smart Money Vision - Trading Bot (MT4)       |
//|                                                                  |
//|  Meme strategie que la version MT5 :                             |
//|   - Entree evaluee UNIQUEMENT a la cloture de la bougie.         |
//|   - Direction = derniere bougie cloturee alignee a la tendance.  |
//|   - TP = 40 pips. Grille martingale x1.6 tous les 80 pips        |
//|     adverses. TP recalcule au prix moyen du panier.              |
//|   - MT4 n'a pas de calendrier natif : filtre de news via une     |
//|     liste horaire manuelle (heure du serveur).                   |
//|                                                                  |
//|  AVERTISSEMENT : la martingale comporte un risque eleve de perte |
//|  totale. A tester en compte DEMO.                                |
//+------------------------------------------------------------------+
#property copyright "SMV Trading Bot"
#property version   "1.00"
#property strict

//====================================================================
//  PARAMETRES
//====================================================================
extern string  s1 = "=== Money Management ===";
extern double  InpInitialLot     = 0.01;    // Lot initial
extern double  InpLotMultiplier  = 1.6;     // Multiplicateur de lot (grille)
extern int     InpMaxGridLevels  = 10;      // Nombre max de niveaux de grille
extern double  InpMaxLot         = 50.0;    // Lot maximum autorise

extern string  s2 = "=== Distances (pips) ===";
extern double  InpPipSize        = 0.00001; // Taille d'un pip (defaut = 0.00001)
extern double  InpTakeProfitPips = 40;      // Take Profit (pips)
extern double  InpGridStepPips    = 80;     // Ecart adverse pour ajouter (pips)

extern string  s3 = "=== Signal ===";
extern bool    InpUseTrendFilter = true;    // Filtre de tendance (MM)
extern int     InpFastMAPeriod   = 20;      // Periode MM rapide
extern int     InpSlowMAPeriod   = 50;      // Periode MM lente
extern int     InpMAMethod       = MODE_EMA;// Methode MM (0=SMA..3=LWMA)
extern int     InpMAPrice        = PRICE_CLOSE; // Prix applique MM

extern string  s4 = "=== Filtre News (manuel) ===";
extern bool    InpUseNewsFilter  = false;   // Activer le filtre de news manuel
extern string  InpNewsTimes      = "13:30,15:00"; // Heures news (serveur), HH:MM,HH:MM
extern int     InpNewsMinsBefore = 30;      // Minutes avant (pause)
extern int     InpNewsMinsAfter  = 30;      // Minutes apres (pause)

extern string  s5 = "=== Divers ===";
extern int     InpMagic          = 20240716;// Magic number
extern int     InpSlippage       = 30;      // Slippage (points)
extern bool    InpCloseAllOnStop = false;   // Fermer tout a l'arret

//====================================================================
//  VARIABLES GLOBALES
//====================================================================
datetime g_lastBarTime = 0;
double   g_pip, g_tpPrice, g_gridPrice;

//+------------------------------------------------------------------+
int OnInit()
{
   g_pip       = InpPipSize;
   g_tpPrice   = InpTakeProfitPips * g_pip;
   g_gridPrice = InpGridStepPips   * g_pip;
   g_lastBarTime = iTime(Symbol(), Period(), 0);
   PrintFormat("SMV_TradingBot MT4 initialise. Pip=%.5f TP=%.1f Grille=%.1f Mult=%.2f",
               g_pip, InpTakeProfitPips, InpGridStepPips, InpLotMultiplier);
   return(INIT_SUCCEEDED);
}

void OnDeinit(const int reason)
{
   if(InpCloseAllOnStop) CloseAllPositions();
}

//+------------------------------------------------------------------+
void OnTick()
{
   ManageBasketTakeProfit();

   datetime barTime = iTime(Symbol(), Period(), 0);
   if(barTime == g_lastBarTime) return;   // pas de nouvelle bougie
   g_lastBarTime = barTime;

   OnNewBar();
}

//+------------------------------------------------------------------+
void OnNewBar()
{
   int nPos = CountPositions();
   if(nPos == 0)
   {
      if(InpUseNewsFilter && IsNewsTime()) { Comment("SMV Bot : entree bloquee (news)"); return; }
      int signal = GetEntrySignal();
      if(signal > 0)      OpenPosition(OP_BUY,  InpInitialLot);
      else if(signal < 0) OpenPosition(OP_SELL, InpInitialLot);
   }
   else
   {
      ManageGrid();
   }
}

//+------------------------------------------------------------------+
//| Signal : derniere bougie cloturee (shift 1) + tendance           |
//+------------------------------------------------------------------+
int GetEntrySignal()
{
   double openPrev  = iOpen(Symbol(),  Period(), 1);
   double closePrev = iClose(Symbol(), Period(), 1);
   int candleDir = 0;
   if(closePrev > openPrev) candleDir = 1;
   else if(closePrev < openPrev) candleDir = -1;
   if(candleDir == 0) return 0;

   if(!InpUseTrendFilter) return candleDir;

   int trendDir = GetTrendDirection();
   if(trendDir == 0) return 0;
   if(candleDir == trendDir) return candleDir;
   return 0;
}

int GetTrendDirection()
{
   double fast = iMA(Symbol(), Period(), InpFastMAPeriod, 0, InpMAMethod, InpMAPrice, 1);
   double slow = iMA(Symbol(), Period(), InpSlowMAPeriod, 0, InpMAMethod, InpMAPrice, 1);
   if(fast > slow) return 1;
   if(fast < slow) return -1;
   return 0;
}

//+------------------------------------------------------------------+
//| Grille : ajoute un lot si le marche part de 80 pips en inverse   |
//+------------------------------------------------------------------+
void ManageGrid()
{
   int nPos = CountPositions();
   if(nPos <= 0 || nPos >= InpMaxGridLevels) return;

   int    basketType = GetBasketType();
   double lastEntry  = GetLastEntryPrice();
   double lastLot    = GetLastEntryLot();
   if(lastEntry <= 0.0) return;

   double bid = MarketInfo(Symbol(), MODE_BID);
   double ask = MarketInfo(Symbol(), MODE_ASK);

   bool addTrade = false;
   if(basketType == OP_BUY  && (lastEntry - ask >= g_gridPrice)) addTrade = true;
   if(basketType == OP_SELL && (bid - lastEntry >= g_gridPrice)) addTrade = true;
   if(!addTrade) return;

   if(InpUseNewsFilter && IsNewsTime()) return;

   double nextLot = NormalizeLot(lastLot * InpLotMultiplier);
   if(nextLot > InpMaxLot) nextLot = InpMaxLot;

   if(basketType == OP_BUY) OpenPosition(OP_BUY,  nextLot);
   else                     OpenPosition(OP_SELL, nextLot);
}

//+------------------------------------------------------------------+
//| TP du panier au prix moyen pondere (+40 pips)                    |
//+------------------------------------------------------------------+
void ManageBasketTakeProfit()
{
   int nPos = CountPositions();
   if(nPos <= 0) return;

   int basketType = GetBasketType();
   double sumLots = 0.0, sumWeighted = 0.0;

   for(int i = OrdersTotal() - 1; i >= 0; i--)
   {
      if(!OrderSelect(i, SELECT_BY_POS, MODE_TRADES)) continue;
      if(OrderSymbol() != Symbol() || OrderMagicNumber() != InpMagic) continue;
      if(OrderType() != OP_BUY && OrderType() != OP_SELL) continue;
      sumLots     += OrderLots();
      sumWeighted += OrderLots() * OrderOpenPrice();
   }
   if(sumLots <= 0.0) return;

   double avgPrice = sumWeighted / sumLots;
   double target = (basketType == OP_BUY) ? NormalizeDouble(avgPrice + g_tpPrice, Digits)
                                          : NormalizeDouble(avgPrice - g_tpPrice, Digits);

   for(int i = OrdersTotal() - 1; i >= 0; i--)
   {
      if(!OrderSelect(i, SELECT_BY_POS, MODE_TRADES)) continue;
      if(OrderSymbol() != Symbol() || OrderMagicNumber() != InpMagic) continue;
      if(OrderType() != OP_BUY && OrderType() != OP_SELL) continue;
      if(MathAbs(OrderTakeProfit() - target) > Point)
      {
         if(!OrderModify(OrderTicket(), OrderOpenPrice(), OrderStopLoss(), target, 0, clrNONE))
            Print("OrderModify echec : ", GetLastError());
      }
   }
}

//====================================================================
//  ORDRES
//====================================================================
void OpenPosition(int type, double lot)
{
   lot = NormalizeLot(lot);
   if(lot <= 0.0) return;

   double price = (type == OP_BUY) ? MarketInfo(Symbol(), MODE_ASK) : MarketInfo(Symbol(), MODE_BID);
   double tp    = (type == OP_BUY) ? NormalizeDouble(price + g_tpPrice, Digits)
                                    : NormalizeDouble(price - g_tpPrice, Digits);

   int ticket = OrderSend(Symbol(), type, lot, price, InpSlippage, 0, tp, "SMV", InpMagic, 0,
                          (type == OP_BUY) ? clrBlue : clrBlack);
   if(ticket < 0)
      PrintFormat("Echec ouverture type=%d lot=%.2f err=%d", type, lot, GetLastError());
}

void CloseAllPositions()
{
   for(int i = OrdersTotal() - 1; i >= 0; i--)
   {
      if(!OrderSelect(i, SELECT_BY_POS, MODE_TRADES)) continue;
      if(OrderSymbol() != Symbol() || OrderMagicNumber() != InpMagic) continue;
      double price = (OrderType() == OP_BUY) ? MarketInfo(Symbol(), MODE_BID) : MarketInfo(Symbol(), MODE_ASK);
      if(OrderType() == OP_BUY || OrderType() == OP_SELL)
         OrderClose(OrderTicket(), OrderLots(), price, InpSlippage, clrNONE);
   }
}

//====================================================================
//  UTILITAIRES PANIER
//====================================================================
int CountPositions()
{
   int c = 0;
   for(int i = OrdersTotal() - 1; i >= 0; i--)
   {
      if(!OrderSelect(i, SELECT_BY_POS, MODE_TRADES)) continue;
      if(OrderSymbol() != Symbol() || OrderMagicNumber() != InpMagic) continue;
      if(OrderType() == OP_BUY || OrderType() == OP_SELL) c++;
   }
   return c;
}

int GetBasketType()
{
   for(int i = OrdersTotal() - 1; i >= 0; i--)
   {
      if(!OrderSelect(i, SELECT_BY_POS, MODE_TRADES)) continue;
      if(OrderSymbol() != Symbol() || OrderMagicNumber() != InpMagic) continue;
      if(OrderType() == OP_BUY || OrderType() == OP_SELL) return OrderType();
   }
   return -1;
}

double GetLastEntryPrice()
{
   datetime latest = 0; double price = 0.0;
   for(int i = OrdersTotal() - 1; i >= 0; i--)
   {
      if(!OrderSelect(i, SELECT_BY_POS, MODE_TRADES)) continue;
      if(OrderSymbol() != Symbol() || OrderMagicNumber() != InpMagic) continue;
      if(OrderType() != OP_BUY && OrderType() != OP_SELL) continue;
      if(OrderOpenTime() >= latest) { latest = OrderOpenTime(); price = OrderOpenPrice(); }
   }
   return price;
}

double GetLastEntryLot()
{
   datetime latest = 0; double lot = InpInitialLot;
   for(int i = OrdersTotal() - 1; i >= 0; i--)
   {
      if(!OrderSelect(i, SELECT_BY_POS, MODE_TRADES)) continue;
      if(OrderSymbol() != Symbol() || OrderMagicNumber() != InpMagic) continue;
      if(OrderType() != OP_BUY && OrderType() != OP_SELL) continue;
      if(OrderOpenTime() >= latest) { latest = OrderOpenTime(); lot = OrderLots(); }
   }
   return lot;
}

double NormalizeLot(double lot)
{
   double minLot  = MarketInfo(Symbol(), MODE_MINLOT);
   double maxLot  = MarketInfo(Symbol(), MODE_MAXLOT);
   double lotStep = MarketInfo(Symbol(), MODE_LOTSTEP);
   if(lotStep <= 0.0) lotStep = 0.01;
   lot = MathRound(lot / lotStep) * lotStep;
   if(lot < minLot) lot = minLot;
   if(lot > maxLot) lot = maxLot;
   return NormalizeDouble(lot, 2);
}

//====================================================================
//  FILTRE NEWS MANUEL (MT4 sans calendrier natif)                   |
//  Format InpNewsTimes = "HH:MM,HH:MM" en heure serveur.            |
//====================================================================
bool IsNewsTime()
{
   if(StringLen(InpNewsTimes) == 0) return false;

   datetime now = TimeCurrent();
   int nowSec = TimeHour(now) * 3600 + TimeMinute(now) * 60 + TimeSeconds(now);

   string parts[];
   int n = StringSplit(InpNewsTimes, ',', parts);
   for(int i = 0; i < n; i++)
   {
      string hm = parts[i];
      StringTrimLeft(hm); StringTrimRight(hm);
      int sep = StringFind(hm, ":");
      if(sep < 0) continue;
      int hh = (int)StringToInteger(StringSubstr(hm, 0, sep));
      int mm = (int)StringToInteger(StringSubstr(hm, sep + 1));
      int evtSec = hh * 3600 + mm * 60;

      if(nowSec >= evtSec - InpNewsMinsBefore * 60 &&
         nowSec <= evtSec + InpNewsMinsAfter  * 60)
         return true;
   }
   return false;
}
//+------------------------------------------------------------------+
