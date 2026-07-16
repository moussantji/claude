//+------------------------------------------------------------------+
//|                                                 SMV_Vision.mq5    |
//|            Smart Money Vision - Indicateur visuel (MT5)          |
//|                                                                  |
//|  Affiche :                                                       |
//|   - Theme graphique : fond blanc sale, bougies haussieres bleues |
//|     et baissieres noires.                                        |
//|   - Buy zones (bleu clair transparent) / Sell zones (rouge clair)|
//|   - Order Blocks (OB) haussiers et baissiers.                    |
//|   - Liquidites (BSL / SSL) sur swing highs / lows.               |
//|   - Panneau d'analyse SMV (biais, structure, zone active).       |
//|   - Gains du jour et de la veille.                               |
//|   - Alerte de News (calendrier economique).                      |
//+------------------------------------------------------------------+
#property copyright "SMV Trading Bot"
#property version   "1.00"
#property strict
#property indicator_chart_window
#property indicator_plots 0

//====================================================================
//  PARAMETRES
//====================================================================
input group           "=== Theme graphique ==="
input bool   InpApplyTheme      = true;                 // Appliquer le theme SMV
input color  InpBullColor       = clrRoyalBlue;         // Bougies haussieres (bleu)
input color  InpBearColor       = clrBlack;             // Bougies baissieres (noir)
input color  InpBackground      = C'245,244,238';       // Fond blanc sale
input color  InpForeground      = clrBlack;             // Texte / axes

input group           "=== Detection Smart Money ==="
input int    InpLookback        = 400;                  // Bougies analysees
input int    InpFractal         = 3;                    // Largeur du fractal (swing)
input int    InpMaxZones        = 4;                    // Nombre max de zones affichees
input double InpImpulseFactor   = 1.5;                  // Force de l'impulsion pour un OB

input group           "=== Couleurs des zones ==="
input color  InpBuyZoneColor    = C'200,225,255';       // Buy zone (bleu clair)
input color  InpSellZoneColor   = C'255,205,205';       // Sell zone (rouge clair)
input color  InpBSLColor        = clrDodgerBlue;        // Liquidite achat (BSL)
input color  InpSSLColor        = clrCrimson;           // Liquidite vente (SSL)

input group           "=== Panneau & P&L ==="
input bool   InpShowPanel       = true;                 // Afficher le panneau
input bool   InpPnLSymbolOnly   = false;                // P&L : symbole courant uniquement
input int    InpPanelX          = 12;                   // Position X du panneau
input int    InpPanelY          = 20;                   // Position Y du panneau

input group           "=== News ==="
input bool   InpUseNews         = true;                 // Afficher / alerter les news
input int    InpNewsImportance  = 2;                    // Importance min (1..3)
input int    InpNewsAlertMins   = 15;                   // Alerte X min avant la news

//====================================================================
//  CONSTANTES
//====================================================================
#define PFX   "SMV_"        // prefixe des objets

datetime g_lastBar = 0;
datetime g_lastNewsAlert = 0;

//+------------------------------------------------------------------+
int OnInit()
{
   IndicatorSetString(INDICATOR_SHORTNAME, "SMV Vision");
   if(InpApplyTheme) ApplyTheme();
   EventSetTimer(2);
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
   EventKillTimer();
   ObjectsDeleteAll(0, PFX);
   ChartRedraw();
}

//+------------------------------------------------------------------+
//| Recalcul principal : detection SMC a chaque nouvelle bougie      |
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

   datetime cur = (datetime)SeriesInfoInteger(_Symbol, _Period, SERIES_LASTBAR_DATE);
   if(cur != g_lastBar)
   {
      g_lastBar = cur;
      AnalyzeSmartMoney();
   }
   return(rates_total);
}

//+------------------------------------------------------------------+
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
   ChartSetInteger(0, CHART_COLOR_VOLUME, InpBullColor);
}

//====================================================================
//  ANALYSE SMART MONEY : swings, liquidites, order blocks, zones    |
//====================================================================
void AnalyzeSmartMoney()
{
   // Nettoyage des objets de zones/liquidites (pas le panneau)
   ObjectsDeleteAll(0, PFX+"OB_");
   ObjectsDeleteAll(0, PFX+"LQ_");

   int f = InpFractal;
   int limit = MathMin(InpLookback, Bars(_Symbol,_Period) - f - 2);

   int zonesBull = 0, zonesBear = 0;
   int liqUp = 0, liqDn = 0;

   for(int i = f + 1; i <= limit && (zonesBull < InpMaxZones || zonesBear < InpMaxZones ||
                                     liqUp < InpMaxZones || liqDn < InpMaxZones); i++)
   {
      // --- Detection des swings (fractals) ---
      if(IsSwingHigh(i, f) && liqUp < InpMaxZones)
      {
         DrawLiquidity("LQ_BSL_"+IntegerToString(i), iTime(_Symbol,_Period,i),
                       iHigh(_Symbol,_Period,i), InpBSLColor, "BSL");
         liqUp++;
      }
      if(IsSwingLow(i, f) && liqDn < InpMaxZones)
      {
         DrawLiquidity("LQ_SSL_"+IntegerToString(i), iTime(_Symbol,_Period,i),
                       iLow(_Symbol,_Period,i), InpSSLColor, "SSL");
         liqDn++;
      }

      // --- Order blocks + zones ---
      // OB haussier : derniere bougie baissiere avant une impulsion haussiere
      if(zonesBull < InpMaxZones && IsBullishOB(i))
      {
         DrawZone("OB_BULL_"+IntegerToString(i), i, InpBuyZoneColor, true);
         zonesBull++;
      }
      // OB baissier : derniere bougie haussiere avant une impulsion baissiere
      if(zonesBear < InpMaxZones && IsBearishOB(i))
      {
         DrawZone("OB_BEAR_"+IntegerToString(i), i, InpSellZoneColor, false);
         zonesBear++;
      }
   }
   ChartRedraw();
}

//+------------------------------------------------------------------+
//| Swing high / low (fractal de largeur f)                          |
//+------------------------------------------------------------------+
bool IsSwingHigh(int i, int f)
{
   double h = iHigh(_Symbol,_Period,i);
   for(int k = 1; k <= f; k++)
      if(iHigh(_Symbol,_Period,i+k) >= h || iHigh(_Symbol,_Period,i-k) >= h)
         return false;
   return true;
}
bool IsSwingLow(int i, int f)
{
   double l = iLow(_Symbol,_Period,i);
   for(int k = 1; k <= f; k++)
      if(iLow(_Symbol,_Period,i+k) <= l || iLow(_Symbol,_Period,i-k) <= l)
         return false;
   return true;
}

//+------------------------------------------------------------------+
//| Order block haussier : bougie i baissiere suivie d'une impulsion |
//| haussiere qui casse le haut de i.                                |
//+------------------------------------------------------------------+
bool IsBullishOB(int i)
{
   double o = iOpen(_Symbol,_Period,i);
   double c = iClose(_Symbol,_Period,i);
   if(c >= o) return false;                       // doit etre baissiere

   double body = MathAbs(c - o);
   if(body <= 0) return false;

   double c1 = iClose(_Symbol,_Period,i-1);
   double o1 = iOpen(_Symbol,_Period,i-1);
   double body1 = MathAbs(c1 - o1);

   // Impulsion haussiere qui depasse le haut de la bougie OB
   bool impulse = (c1 > o1) && (body1 >= body * InpImpulseFactor) &&
                  (iClose(_Symbol,_Period,i-1) > iHigh(_Symbol,_Period,i));
   return impulse;
}

//+------------------------------------------------------------------+
//| Order block baissier : bougie i haussiere suivie d'une impulsion |
//| baissiere qui casse le bas de i.                                 |
//+------------------------------------------------------------------+
bool IsBearishOB(int i)
{
   double o = iOpen(_Symbol,_Period,i);
   double c = iClose(_Symbol,_Period,i);
   if(c <= o) return false;                       // doit etre haussiere

   double body = MathAbs(c - o);
   if(body <= 0) return false;

   double c1 = iClose(_Symbol,_Period,i-1);
   double o1 = iOpen(_Symbol,_Period,i-1);
   double body1 = MathAbs(c1 - o1);

   bool impulse = (c1 < o1) && (body1 >= body * InpImpulseFactor) &&
                  (iClose(_Symbol,_Period,i-1) < iLow(_Symbol,_Period,i));
   return impulse;
}

//====================================================================
//  DESSIN
//====================================================================
void DrawZone(string name, int i, color clr, bool isBuy)
{
   string obj = PFX + name;
   datetime t1 = iTime(_Symbol,_Period,i);
   datetime t2 = t1 + PeriodSeconds(_Period) * 20;   // projection vers la droite
   double top = iHigh(_Symbol,_Period,i);
   double bot = iLow(_Symbol,_Period,i);

   if(ObjectFind(0, obj) < 0)
      ObjectCreate(0, obj, OBJ_RECTANGLE, 0, t1, top, t2, bot);
   ObjectSetInteger(0, obj, OBJPROP_TIME, 0, t1);
   ObjectSetInteger(0, obj, OBJPROP_TIME, 1, t2);
   ObjectSetDouble (0, obj, OBJPROP_PRICE, 0, top);
   ObjectSetDouble (0, obj, OBJPROP_PRICE, 1, bot);
   ObjectSetInteger(0, obj, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, obj, OBJPROP_FILL, true);      // rempli
   ObjectSetInteger(0, obj, OBJPROP_BACK, true);      // derriere les bougies => effet transparent
   ObjectSetInteger(0, obj, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, obj, OBJPROP_HIDDEN, true);
   ObjectSetString (0, obj, OBJPROP_TOOLTIP, isBuy ? "SMV Buy Zone / Bullish OB" : "SMV Sell Zone / Bearish OB");

   // Etiquette de zone
   string lbl = obj + "_lbl";
   if(ObjectFind(0, lbl) < 0)
      ObjectCreate(0, lbl, OBJ_TEXT, 0, t1, isBuy ? bot : top);
   ObjectSetInteger(0, lbl, OBJPROP_TIME, 0, t1);
   ObjectSetDouble (0, lbl, OBJPROP_PRICE, 0, isBuy ? bot : top);
   ObjectSetString (0, lbl, OBJPROP_TEXT, isBuy ? "Buy Zone (OB+)" : "Sell Zone (OB-)");
   ObjectSetInteger(0, lbl, OBJPROP_COLOR, isBuy ? InpBSLColor : InpSSLColor);
   ObjectSetInteger(0, lbl, OBJPROP_FONTSIZE, 7);
   ObjectSetInteger(0, lbl, OBJPROP_ANCHOR, isBuy ? ANCHOR_LEFT_UPPER : ANCHOR_LEFT_LOWER);
   ObjectSetInteger(0, lbl, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, lbl, OBJPROP_HIDDEN, true);
}

void DrawLiquidity(string name, datetime t, double price, color clr, string tag)
{
   string obj = PFX + name;
   datetime t2 = t + PeriodSeconds(_Period) * 30;
   if(ObjectFind(0, obj) < 0)
      ObjectCreate(0, obj, OBJ_TREND, 0, t, price, t2, price);
   ObjectSetInteger(0, obj, OBJPROP_TIME, 0, t);
   ObjectSetInteger(0, obj, OBJPROP_TIME, 1, t2);
   ObjectSetDouble (0, obj, OBJPROP_PRICE, 0, price);
   ObjectSetDouble (0, obj, OBJPROP_PRICE, 1, price);
   ObjectSetInteger(0, obj, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, obj, OBJPROP_STYLE, STYLE_DOT);
   ObjectSetInteger(0, obj, OBJPROP_WIDTH, 1);
   ObjectSetInteger(0, obj, OBJPROP_RAY_RIGHT, false);
   ObjectSetInteger(0, obj, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, obj, OBJPROP_HIDDEN, true);
   ObjectSetString (0, obj, OBJPROP_TOOLTIP, tag + " liquidity");

   string lbl = obj + "_lbl";
   if(ObjectFind(0, lbl) < 0)
      ObjectCreate(0, lbl, OBJ_TEXT, 0, t2, price);
   ObjectSetInteger(0, lbl, OBJPROP_TIME, 0, t2);
   ObjectSetDouble (0, lbl, OBJPROP_PRICE, 0, price);
   ObjectSetString (0, lbl, OBJPROP_TEXT, " "+tag);
   ObjectSetInteger(0, lbl, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, lbl, OBJPROP_FONTSIZE, 7);
   ObjectSetInteger(0, lbl, OBJPROP_ANCHOR, ANCHOR_LEFT);
   ObjectSetInteger(0, lbl, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, lbl, OBJPROP_HIDDEN, true);
}

//====================================================================
//  PANNEAU D'ANALYSE + P&L + NEWS
//====================================================================
void UpdatePanel()
{
   // Fond du panneau
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
      ObjectSetInteger(0, bg, OBJPROP_WIDTH, 1);
      ObjectSetInteger(0, bg, OBJPROP_BACK, false);
      ObjectSetInteger(0, bg, OBJPROP_SELECTABLE, false);
      ObjectSetInteger(0, bg, OBJPROP_HIDDEN, true);
   }

   // Analyse
   int bias = GetBias();
   string biasTxt = (bias > 0) ? "HAUSSIER (Bullish)" : (bias < 0) ? "BAISSIER (Bearish)" : "NEUTRE";
   color  biasClr = (bias > 0) ? InpBullColor : (bias < 0) ? InpSSLColor : clrGray;
   string structTxt = GetStructureText();

   double pnlToday = GetDayProfit(0);
   double pnlYest  = GetDayProfit(1);
   color todayClr  = (pnlToday >= 0) ? InpBullColor : InpSSLColor;
   color yestClr   = (pnlYest  >= 0) ? InpBullColor : InpSSLColor;

   string newsTxt = GetNextNewsText();

   int x = InpPanelX + 10;
   int y = InpPanelY + 8;
   PanelLabel("T_TITLE", x, y,      "SMART MONEY VISION", InpForeground, 10, true); y += 22;
   PanelLabel("T_SYM",   x, y,      _Symbol+"  |  "+TFToStr(_Period), InpForeground, 8, false); y += 20;
   PanelLabel("T_BIAS",  x, y,      "Biais : "+biasTxt, biasClr, 9, true); y += 18;
   PanelLabel("T_STRUCT",x, y,      "Structure : "+structTxt, InpForeground, 8, false); y += 22;
   PanelLabel("T_PNLT",  x, y,      "Gain aujourd'hui : "+DoubleToString(pnlToday,2), todayClr, 9, true); y += 18;
   PanelLabel("T_PNLY",  x, y,      "Gain hier         : "+DoubleToString(pnlYest,2), yestClr, 9, true); y += 22;
   PanelLabel("T_NEWS",  x, y,      "News : "+newsTxt, clrDarkOrange, 8, false);
}

void PanelLabel(string id, int x, int y, string text, color clr, int size, bool bold)
{
   string obj = PFX + "PL_" + id;
   if(ObjectFind(0, obj) < 0)
   {
      ObjectCreate(0, obj, OBJ_LABEL, 0, 0, 0);
      ObjectSetInteger(0, obj, OBJPROP_CORNER, CORNER_LEFT_UPPER);
      ObjectSetInteger(0, obj, OBJPROP_SELECTABLE, false);
      ObjectSetInteger(0, obj, OBJPROP_HIDDEN, true);
   }
   ObjectSetInteger(0, obj, OBJPROP_XDISTANCE, x);
   ObjectSetInteger(0, obj, OBJPROP_YDISTANCE, y);
   ObjectSetString (0, obj, OBJPROP_TEXT, text);
   ObjectSetInteger(0, obj, OBJPROP_COLOR, clr);
   ObjectSetInteger(0, obj, OBJPROP_FONTSIZE, size);
   ObjectSetString (0, obj, OBJPROP_FONT, bold ? "Arial Bold" : "Arial");
}

//+------------------------------------------------------------------+
//| Biais base sur la structure des derniers swings                  |
//+------------------------------------------------------------------+
int GetBias()
{
   double lastHigh=0, prevHigh=0, lastLow=0, prevLow=0;
   int fh=0, fl=0;
   int f = InpFractal;
   for(int i = f+1; i < InpLookback && (fh<2 || fl<2); i++)
   {
      if(fh<2 && IsSwingHigh(i,f)) { if(fh==0) lastHigh=iHigh(_Symbol,_Period,i); else prevHigh=iHigh(_Symbol,_Period,i); fh++; }
      if(fl<2 && IsSwingLow(i,f))  { if(fl==0) lastLow =iLow(_Symbol,_Period,i);  else prevLow =iLow(_Symbol,_Period,i);  fl++; }
   }
   if(fh<2 || fl<2) return 0;
   bool hh = lastHigh > prevHigh;
   bool hl = lastLow  > prevLow;
   bool lh = lastHigh < prevHigh;
   bool ll = lastLow  < prevLow;
   if(hh && hl) return 1;
   if(lh && ll) return -1;
   return 0;
}

string GetStructureText()
{
   int b = GetBias();
   if(b > 0) return "HH/HL - BOS haussier";
   if(b < 0) return "LH/LL - BOS baissier";
   return "Range / consolidation";
}

//====================================================================
//  P&L JOUR / VEILLE (historique des deals)                         |
//====================================================================
double GetDayProfit(int dayShift)   // 0 = aujourd'hui, 1 = hier
{
   MqlDateTime st;
   TimeToStruct(TimeCurrent(), st);
   st.hour = 0; st.min = 0; st.sec = 0;
   datetime midnight = StructToTime(st);
   datetime from = midnight - dayShift * 86400;
   datetime to   = from + 86400;

   if(!HistorySelect(from, to)) return 0.0;

   double profit = 0.0;
   int total = HistoryDealsTotal();
   for(int i = 0; i < total; i++)
   {
      ulong ticket = HistoryDealGetTicket(i);
      if(ticket == 0) continue;
      if(HistoryDealGetInteger(ticket, DEAL_ENTRY) != DEAL_ENTRY_OUT) continue;
      if(InpPnLSymbolOnly && HistoryDealGetString(ticket, DEAL_SYMBOL) != _Symbol) continue;
      profit += HistoryDealGetDouble(ticket, DEAL_PROFIT)
              + HistoryDealGetDouble(ticket, DEAL_SWAP)
              + HistoryDealGetDouble(ticket, DEAL_COMMISSION);
   }
   return profit;
}

//====================================================================
//  NEWS
//====================================================================
string GetNextNewsText()
{
   datetime now = TimeCurrent();
   datetime to  = now + 24*3600;
   string baseCcy   = SymbolInfoString(_Symbol, SYMBOL_CURRENCY_BASE);
   string profitCcy = SymbolInfoString(_Symbol, SYMBOL_CURRENCY_PROFIT);

   MqlCalendarValue values[];
   int total = CalendarValueHistory(values, now, to, NULL, NULL);
   datetime best = 0;
   string   bestName = "";
   for(int i = 0; i < total; i++)
   {
      MqlCalendarEvent ev;
      if(!CalendarEventById(values[i].event_id, ev)) continue;
      if((int)ev.importance < InpNewsImportance) continue;
      MqlCalendarCountry country;
      if(CalendarCountryById(ev.country_id, country))
      {
         if(country.currency != baseCcy && country.currency != profitCcy) continue;
      }
      if(values[i].time > now && (best == 0 || values[i].time < best))
      {
         best = values[i].time;
         bestName = ev.name;
      }
   }
   if(best == 0) return "aucune (24h)";
   int mins = (int)((best - now) / 60);
   return StringFormat("%s dans %dmin", StringSubstr(bestName,0,18), mins);
}

void CheckNewsAlert()
{
   datetime now = TimeCurrent();
   datetime to  = now + InpNewsAlertMins*60;
   string baseCcy   = SymbolInfoString(_Symbol, SYMBOL_CURRENCY_BASE);
   string profitCcy = SymbolInfoString(_Symbol, SYMBOL_CURRENCY_PROFIT);

   MqlCalendarValue values[];
   int total = CalendarValueHistory(values, now, to, NULL, NULL);
   for(int i = 0; i < total; i++)
   {
      MqlCalendarEvent ev;
      if(!CalendarEventById(values[i].event_id, ev)) continue;
      if((int)ev.importance < InpNewsImportance) continue;
      MqlCalendarCountry country;
      if(CalendarCountryById(ev.country_id, country))
      {
         if(country.currency != baseCcy && country.currency != profitCcy) continue;
      }
      if(values[i].time > now && values[i].time != g_lastNewsAlert)
      {
         g_lastNewsAlert = values[i].time;
         Alert("SMV News : ", ev.name, " a venir sur ", _Symbol);
         break;
      }
   }
}

//====================================================================
//  UTIL
//====================================================================
string TFToStr(ENUM_TIMEFRAMES tf)
{
   string s = EnumToString(tf);
   StringReplace(s, "PERIOD_", "");
   return s;
}
//+------------------------------------------------------------------+
