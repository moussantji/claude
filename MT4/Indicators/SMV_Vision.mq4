//+------------------------------------------------------------------+
//|                                                 SMV_Vision.mq4    |
//|            Smart Money Vision - Indicateur visuel (MT4)          |
//|                                                                  |
//|  Affiche : theme (fond blanc sale, bougies bleues/noires),      |
//|  buy zones (bleu clair) / sell zones (rouge clair), order blocks,|
//|  liquidites (BSL/SSL), panneau d'analyse SMV, gains jour/veille, |
//|  et alerte de news (liste horaire manuelle - MT4 sans calendrier)|
//+------------------------------------------------------------------+
#property copyright "SMV Trading Bot"
#property version   "1.00"
#property strict
#property indicator_chart_window

//====================================================================
//  PARAMETRES
//====================================================================
extern string s1 = "=== Theme ===";
extern bool   InpApplyTheme     = true;
extern color  InpBullColor      = clrRoyalBlue;    // Bougies haussieres (bleu)
extern color  InpBearColor      = clrBlack;        // Bougies baissieres (noir)
extern color  InpBackground     = C'245,244,238';  // Fond blanc sale
extern color  InpForeground     = clrBlack;

extern string s2 = "=== Smart Money ===";
extern int    InpLookback       = 400;
extern int    InpFractal        = 3;
extern int    InpMaxZones       = 4;
extern double InpImpulseFactor  = 1.5;

extern string s3 = "=== Couleurs zones ===";
extern color  InpBuyZoneColor   = C'200,225,255';  // Buy zone (bleu clair)
extern color  InpSellZoneColor  = C'255,205,205';  // Sell zone (rouge clair)
extern color  InpBSLColor       = clrDodgerBlue;
extern color  InpSSLColor       = clrCrimson;

extern string s4 = "=== Panneau & P&L ===";
extern bool   InpShowPanel      = true;
extern bool   InpPnLSymbolOnly  = false;
extern int    InpMagicFilter    = 0;               // 0 = tous magic
extern int    InpPanelX         = 12;
extern int    InpPanelY         = 20;

extern string s5 = "=== News (manuel MT4) ===";
extern bool   InpUseNews        = true;
extern string InpNewsTimes      = "13:30,15:00";   // Heures serveur HH:MM,HH:MM
extern int    InpNewsAlertMins  = 15;

#define PFX "SMV_"
datetime g_lastBar = 0;
string   g_alerted = "";

//+------------------------------------------------------------------+
int OnInit()
{
   IndicatorShortName("SMV Vision");
   if(InpApplyTheme) ApplyTheme();
   EventSetTimer(2);
   return(INIT_SUCCEEDED);
}

void OnDeinit(const int reason)
{
   EventKillTimer();
   ObjectsDeleteAll(0, PFX);
   ChartRedraw();
}

//+------------------------------------------------------------------+
int OnCalculate(const int rates_total,
                const int prev_calculated,
                const datetime &time[],
                const double &open[],
                const double &high[],
                const double &low[],
                const double &close[],
                const long &tick_volume[],
                const long &volume[],
                const int &spread[])
{
   if(rates_total < InpLookback + InpFractal + 5) return(rates_total);
   datetime cur = iTime(Symbol(), Period(), 0);
   if(cur != g_lastBar)
   {
      g_lastBar = cur;
      AnalyzeSmartMoney();
   }
   return(rates_total);
}

void OnTimer()
{
   if(InpShowPanel) UpdatePanel();
   if(InpUseNews)   CheckNewsAlert();
   ChartRedraw();
}

//====================================================================
//  THEME
//====================================================================
void ApplyTheme()
{
   ChartSetInteger(0, CHART_MODE, CHART_CANDLES);
   ChartSetInteger(0, CHART_COLOR_BACKGROUND, InpBackground);
   ChartSetInteger(0, CHART_COLOR_FOREGROUND, InpForeground);
   ChartSetInteger(0, CHART_COLOR_GRID, C'225,224,218');
   ChartSetInteger(0, CHART_COLOR_CANDLE_BULL, InpBullColor);
   ChartSetInteger(0, CHART_COLOR_CANDLE_BEAR, InpBearColor);
   ChartSetInteger(0, CHART_COLOR_CHART_UP, InpBullColor);
   ChartSetInteger(0, CHART_COLOR_CHART_DOWN, InpBearColor);
   ChartSetInteger(0, CHART_COLOR_CHART_LINE, InpForeground);
   ChartSetInteger(0, CHART_SHOW_GRID, false);
}

//====================================================================
//  ANALYSE SMART MONEY
//====================================================================
void AnalyzeSmartMoney()
{
   ObjectsDeleteAll(0, PFX+"OB_");
   ObjectsDeleteAll(0, PFX+"LQ_");

   int f = InpFractal;
   int limit = MathMin(InpLookback, Bars - f - 2);
   int zonesBull=0, zonesBear=0, liqUp=0, liqDn=0;

   for(int i = f+1; i <= limit; i++)
   {
      if(zonesBull>=InpMaxZones && zonesBear>=InpMaxZones && liqUp>=InpMaxZones && liqDn>=InpMaxZones)
         break;

      if(liqUp < InpMaxZones && IsSwingHigh(i,f))
      { DrawLiquidity("LQ_BSL_"+IntegerToString(i), iTime(Symbol(),Period(),i), iHigh(Symbol(),Period(),i), InpBSLColor, "BSL"); liqUp++; }
      if(liqDn < InpMaxZones && IsSwingLow(i,f))
      { DrawLiquidity("LQ_SSL_"+IntegerToString(i), iTime(Symbol(),Period(),i), iLow(Symbol(),Period(),i), InpSSLColor, "SSL"); liqDn++; }

      if(zonesBull < InpMaxZones && IsBullishOB(i))
      { DrawZone("OB_BULL_"+IntegerToString(i), i, InpBuyZoneColor, true); zonesBull++; }
      if(zonesBear < InpMaxZones && IsBearishOB(i))
      { DrawZone("OB_BEAR_"+IntegerToString(i), i, InpSellZoneColor, false); zonesBear++; }
   }
   ChartRedraw();
}

bool IsSwingHigh(int i, int f)
{
   double h = iHigh(Symbol(),Period(),i);
   for(int k=1;k<=f;k++)
      if(iHigh(Symbol(),Period(),i+k) >= h || iHigh(Symbol(),Period(),i-k) >= h) return false;
   return true;
}
bool IsSwingLow(int i, int f)
{
   double l = iLow(Symbol(),Period(),i);
   for(int k=1;k<=f;k++)
      if(iLow(Symbol(),Period(),i+k) <= l || iLow(Symbol(),Period(),i-k) <= l) return false;
   return true;
}

bool IsBullishOB(int i)
{
   double o=iOpen(Symbol(),Period(),i), c=iClose(Symbol(),Period(),i);
   if(c >= o) return false;
   double body = MathAbs(c-o); if(body<=0) return false;
   double o1=iOpen(Symbol(),Period(),i-1), c1=iClose(Symbol(),Period(),i-1);
   double body1 = MathAbs(c1-o1);
   return (c1>o1) && (body1 >= body*InpImpulseFactor) && (c1 > iHigh(Symbol(),Period(),i));
}
bool IsBearishOB(int i)
{
   double o=iOpen(Symbol(),Period(),i), c=iClose(Symbol(),Period(),i);
   if(c <= o) return false;
   double body = MathAbs(c-o); if(body<=0) return false;
   double o1=iOpen(Symbol(),Period(),i-1), c1=iClose(Symbol(),Period(),i-1);
   double body1 = MathAbs(c1-o1);
   return (c1<o1) && (body1 >= body*InpImpulseFactor) && (c1 < iLow(Symbol(),Period(),i));
}

//====================================================================
//  DESSIN
//====================================================================
void DrawZone(string name, int i, color clr, bool isBuy)
{
   string obj = PFX + name;
   datetime t1 = iTime(Symbol(),Period(),i);
   datetime t2 = t1 + Period()*60*20;
   double top = iHigh(Symbol(),Period(),i);
   double bot = iLow(Symbol(),Period(),i);

   if(ObjectFind(0, obj) < 0) ObjectCreate(0, obj, OBJ_RECTANGLE, 0, t1, top, t2, bot);
   ObjectSetInteger(0, obj, OBJPROP_TIME1, t1);
   ObjectSetInteger(0, obj, OBJPROP_TIME2, t2);
   ObjectSetDouble (0, obj, OBJPROP_PRICE1, top);
   ObjectSetDouble (0, obj, OBJPROP_PRICE2, bot);
   ObjectSetInteger(0, obj, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, obj, OBJPROP_BACK, true);     // rempli + derriere => effet transparent
   ObjectSetInteger(0, obj, OBJPROP_SELECTABLE, false);
   ObjectSetString (0, obj, OBJPROP_TOOLTIP, isBuy ? "SMV Buy Zone / Bullish OB" : "SMV Sell Zone / Bearish OB");

   string lbl = obj + "_lbl";
   if(ObjectFind(0, lbl) < 0) ObjectCreate(0, lbl, OBJ_TEXT, 0, t1, isBuy?bot:top);
   ObjectSetInteger(0, lbl, OBJPROP_TIME1, t1);
   ObjectSetDouble (0, lbl, OBJPROP_PRICE1, isBuy?bot:top);
   ObjectSetString (0, lbl, OBJPROP_TEXT, isBuy ? "Buy Zone (OB+)" : "Sell Zone (OB-)");
   ObjectSetInteger(0, lbl, OBJPROP_COLOR, isBuy ? InpBSLColor : InpSSLColor);
   ObjectSetInteger(0, lbl, OBJPROP_FONTSIZE, 7);
   ObjectSetInteger(0, lbl, OBJPROP_SELECTABLE, false);
}

void DrawLiquidity(string name, datetime t, double price, color clr, string tag)
{
   string obj = PFX + name;
   datetime t2 = t + Period()*60*30;
   if(ObjectFind(0, obj) < 0) ObjectCreate(0, obj, OBJ_TREND, 0, t, price, t2, price);
   ObjectSetInteger(0, obj, OBJPROP_TIME1, t);
   ObjectSetInteger(0, obj, OBJPROP_TIME2, t2);
   ObjectSetDouble (0, obj, OBJPROP_PRICE1, price);
   ObjectSetDouble (0, obj, OBJPROP_PRICE2, price);
   ObjectSetInteger(0, obj, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, obj, OBJPROP_STYLE, STYLE_DOT);
   ObjectSetInteger(0, obj, OBJPROP_RAY_RIGHT, false);
   ObjectSetInteger(0, obj, OBJPROP_SELECTABLE, false);
   ObjectSetString (0, obj, OBJPROP_TOOLTIP, tag+" liquidity");

   string lbl = obj + "_lbl";
   if(ObjectFind(0, lbl) < 0) ObjectCreate(0, lbl, OBJ_TEXT, 0, t2, price);
   ObjectSetInteger(0, lbl, OBJPROP_TIME1, t2);
   ObjectSetDouble (0, lbl, OBJPROP_PRICE1, price);
   ObjectSetString (0, lbl, OBJPROP_TEXT, " "+tag);
   ObjectSetInteger(0, lbl, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, lbl, OBJPROP_FONTSIZE, 7);
   ObjectSetInteger(0, lbl, OBJPROP_SELECTABLE, false);
}

//====================================================================
//  PANNEAU
//====================================================================
void UpdatePanel()
{
   string bg = PFX + "PANEL_BG";
   if(ObjectFind(0, bg) < 0)
   {
      ObjectCreate(0, bg, OBJ_RECTANGLE_LABEL, 0, 0, 0);
      ObjectSetInteger(0, bg, OBJPROP_CORNER, CORNER_LEFT_UPPER);
      ObjectSetInteger(0, bg, OBJPROP_XDISTANCE, InpPanelX);
      ObjectSetInteger(0, bg, OBJPROP_YDISTANCE, InpPanelY);
      ObjectSetInteger(0, bg, OBJPROP_XSIZE, 250);
      ObjectSetInteger(0, bg, OBJPROP_YSIZE, 178);
      ObjectSetInteger(0, bg, OBJPROP_BGCOLOR, C'252,252,250');
      ObjectSetInteger(0, bg, OBJPROP_BORDER_TYPE, BORDER_FLAT);
      ObjectSetInteger(0, bg, OBJPROP_COLOR, C'210,210,205');
      ObjectSetInteger(0, bg, OBJPROP_BACK, false);
      ObjectSetInteger(0, bg, OBJPROP_SELECTABLE, false);
   }

   int bias = GetBias();
   string biasTxt = (bias>0)?"HAUSSIER (Bullish)":(bias<0)?"BAISSIER (Bearish)":"NEUTRE";
   color  biasClr = (bias>0)?InpBullColor:(bias<0)?InpSSLColor:clrGray;
   string structTxt = GetStructureText();

   double pnlToday = GetDayProfit(0);
   double pnlYest  = GetDayProfit(1);
   color todayClr  = (pnlToday>=0)?InpBullColor:InpSSLColor;
   color yestClr   = (pnlYest >=0)?InpBullColor:InpSSLColor;

   string newsTxt = GetNextNewsText();

   int x = InpPanelX + 10, y = InpPanelY + 8;
   PanelLabel("T_TITLE", x, y, "SMART MONEY VISION", InpForeground, 10, true); y += 22;
   PanelLabel("T_SYM",   x, y, Symbol()+"  |  "+TFToStr(Period()), InpForeground, 8, false); y += 20;
   PanelLabel("T_BIAS",  x, y, "Biais : "+biasTxt, biasClr, 9, true); y += 18;
   PanelLabel("T_STRUCT",x, y, "Structure : "+structTxt, InpForeground, 8, false); y += 22;
   PanelLabel("T_PNLT",  x, y, "Gain aujourd'hui : "+DoubleToString(pnlToday,2), todayClr, 9, true); y += 18;
   PanelLabel("T_PNLY",  x, y, "Gain hier         : "+DoubleToString(pnlYest,2), yestClr, 9, true); y += 22;
   PanelLabel("T_NEWS",  x, y, "News : "+newsTxt, clrDarkOrange, 8, false);
}

void PanelLabel(string id, int x, int y, string text, color clr, int size, bool bold)
{
   string obj = PFX + "PL_" + id;
   if(ObjectFind(0, obj) < 0)
   {
      ObjectCreate(0, obj, OBJ_LABEL, 0, 0, 0);
      ObjectSetInteger(0, obj, OBJPROP_CORNER, CORNER_LEFT_UPPER);
      ObjectSetInteger(0, obj, OBJPROP_SELECTABLE, false);
   }
   ObjectSetInteger(0, obj, OBJPROP_XDISTANCE, x);
   ObjectSetInteger(0, obj, OBJPROP_YDISTANCE, y);
   ObjectSetString (0, obj, OBJPROP_TEXT, text);
   ObjectSetInteger(0, obj, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, obj, OBJPROP_FONTSIZE, size);
   ObjectSetString (0, obj, OBJPROP_FONT, bold ? "Arial Bold" : "Arial");
}

int GetBias()
{
   double lastHigh=0, prevHigh=0, lastLow=0, prevLow=0;
   int fh=0, fl=0, f=InpFractal;
   for(int i=f+1; i<InpLookback && (fh<2||fl<2); i++)
   {
      if(fh<2 && IsSwingHigh(i,f)) { if(fh==0) lastHigh=iHigh(Symbol(),Period(),i); else prevHigh=iHigh(Symbol(),Period(),i); fh++; }
      if(fl<2 && IsSwingLow(i,f))  { if(fl==0) lastLow =iLow(Symbol(),Period(),i);  else prevLow =iLow(Symbol(),Period(),i);  fl++; }
   }
   if(fh<2 || fl<2) return 0;
   if(lastHigh>prevHigh && lastLow>prevLow) return 1;
   if(lastHigh<prevHigh && lastLow<prevLow) return -1;
   return 0;
}

string GetStructureText()
{
   int b = GetBias();
   if(b>0) return "HH/HL - BOS haussier";
   if(b<0) return "LH/LL - BOS baissier";
   return "Range / consolidation";
}

//====================================================================
//  P&L JOUR / VEILLE (historique MT4)
//====================================================================
double GetDayProfit(int dayShift)
{
   datetime now = TimeCurrent();
   datetime midnight = now - (now % 86400);   // minuit (heure serveur)
   datetime from = midnight - dayShift*86400;
   datetime to   = from + 86400;

   double profit = 0.0;
   for(int i = OrdersHistoryTotal() - 1; i >= 0; i--)
   {
      if(!OrderSelect(i, SELECT_BY_POS, MODE_HISTORY)) continue;
      if(OrderType() != OP_BUY && OrderType() != OP_SELL) continue;
      if(OrderCloseTime() < from || OrderCloseTime() >= to) continue;
      if(InpPnLSymbolOnly && OrderSymbol() != Symbol()) continue;
      if(InpMagicFilter != 0 && OrderMagicNumber() != InpMagicFilter) continue;
      profit += OrderProfit() + OrderSwap() + OrderCommission();
   }
   return profit;
}

//====================================================================
//  NEWS (liste horaire manuelle)
//====================================================================
string GetNextNewsText()
{
   if(StringLen(InpNewsTimes) == 0) return "n/a";
   datetime now = TimeCurrent();
   int nowSec = TimeHour(now)*3600 + TimeMinute(now)*60 + TimeSeconds(now);

   string parts[]; int n = StringSplit(InpNewsTimes, ',', parts);
   int bestDelta = 999999;
   for(int i=0;i<n;i++)
   {
      string hm = parts[i]; StringTrimLeft(hm); StringTrimRight(hm);
      int sep = StringFind(hm, ":"); if(sep<0) continue;
      int evt = (int)StringToInteger(StringSubstr(hm,0,sep))*3600 + (int)StringToInteger(StringSubstr(hm,sep+1))*60;
      int delta = evt - nowSec;
      if(delta > 0 && delta < bestDelta) bestDelta = delta;
   }
   if(bestDelta == 999999) return "aucune aujourd'hui";
   return StringFormat("dans %dmin", bestDelta/60);
}

void CheckNewsAlert()
{
   if(StringLen(InpNewsTimes) == 0) return;
   datetime now = TimeCurrent();
   int nowSec = TimeHour(now)*3600 + TimeMinute(now)*60 + TimeSeconds(now);

   string parts[]; int n = StringSplit(InpNewsTimes, ',', parts);
   for(int i=0;i<n;i++)
   {
      string hm = parts[i]; StringTrimLeft(hm); StringTrimRight(hm);
      int sep = StringFind(hm, ":"); if(sep<0) continue;
      int evt = (int)StringToInteger(StringSubstr(hm,0,sep))*3600 + (int)StringToInteger(StringSubstr(hm,sep+1))*60;
      int delta = evt - nowSec;
      if(delta > 0 && delta <= InpNewsAlertMins*60)
      {
         string key = TimeToString(now, TIME_DATE) + hm;
         if(g_alerted != key)
         {
            g_alerted = key;
            Alert("SMV News : evenement a ", hm, " (dans ", delta/60, " min) sur ", Symbol());
         }
      }
   }
}

//====================================================================
string TFToStr(int tf)
{
   switch(tf)
   {
      case PERIOD_M1:  return "M1";
      case PERIOD_M5:  return "M5";
      case PERIOD_M15: return "M15";
      case PERIOD_M30: return "M30";
      case PERIOD_H1:  return "H1";
      case PERIOD_H4:  return "H4";
      case PERIOD_D1:  return "D1";
      case PERIOD_W1:  return "W1";
      case PERIOD_MN1: return "MN1";
   }
   return IntegerToString(tf);
}
//+------------------------------------------------------------------+
