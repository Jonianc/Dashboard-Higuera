const $ = s=>document.querySelector(s);
const $$ = s=>Array.from(document.querySelectorAll(s));
const fmt = n => (n===null || n===undefined || Number.isNaN(n)) ? "—" : Math.round(n).toLocaleString('es-CL');
const pctFmt = x => (x===null||x===undefined||Number.isNaN(x)) ? "—" : Math.round(x*100).toLocaleString('es-CL') + '%';
const strip = s => String(s||'').trim();
const normalizeFaenaName = v => normalizeHeader(String(v||'')).replace(/\s+/g,' ').trim();
const isInversionFaena = v => normalizeFaenaName(v)==='INVERSIONES VARIAS';
const normalizeInversionToken = v => normalizeHeader(String(v||'')).replace(/\s+/g,' ').trim();
const isInversionRow = row => {
  const faena = normalizeInversionToken(row && row.FAENA);
  const nivel2 = normalizeInversionToken(row && row.NIVEL_2);
  const nivelTransversal = normalizeInversionToken(row && row.NIVEL_TRANSVERSAL);
  return faena === 'INVERSIONES VARIAS' || nivel2 === 'INVERSIONES' || nivelTransversal === 'INVERSIONES';
};
function splitLines(text){ return text.replace(/\r\n/g,'\n').replace(/\r/g,'\n').split('\n'); }
function detectDelimiter(text){
  const sample = splitLines(text).slice(0,20).filter(l=>strip(l));
  const countOutsideQuotes = (line, delim) => {
    let q = false, total = 0;
    for(let i=0;i<line.length;i++){
      const ch = line[i];
      if(ch === '"'){
        if(q && line[i+1] === '"'){ i++; continue; }
        q = !q;
        continue;
      }
      if(!q && ch === delim) total++;
    }
    return total;
  };
  const candidates = [';', ',', '	', '|'].map(d=>{
    const counts = sample.map(line => countOutsideQuotes(line, d));
    const positives = counts.filter(n => n > 0);
    const score = positives.reduce((a,n)=>a+n,0);
    const consistency = positives.length ? Math.min(...positives) : 0;
    return {d, score, consistency, hits: positives.length};
  });
  candidates.sort((a,b)=>{
    if(b.hits !== a.hits) return b.hits - a.hits;
    if(b.consistency !== a.consistency) return b.consistency - a.consistency;
    return b.score - a.score;
  });
  return candidates[0] ? candidates[0].d : ';';
}
function parseCSV(text, delim){
  const lines = splitLines(text);
  const rows = lines.map(l=>{
    const out=[]; let cur='', q=false;
    for(let i=0;i<l.length;i++){ const ch=l[i];
      if(ch==='"'){ q=!q; continue; }
      if(!q && (ch===delim || (delim==='\t' && ch==='\t'))){ out.push(cur); cur=''; continue; }
      cur+=ch;
    }
    out.push(cur);
    return out.map(x=>x.trim());
  });
  return rows;
}
function normalizeHeader(s){ try{return s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toUpperCase();}catch(e){return String(s).toUpperCase();} }
function findHeader(rows){
  const req = ["FECHA","TEMPORADA","PREDIO","SECTOR","CUARTEL","FAENA","NIVEL 1","SUPERFICIE REAL (HA)","TOTAL CUARTEL"];
  let best=-1,score=-1;
  for(let i=0;i<Math.min(rows.length,300);i++){
    const up = rows[i].map(normalizeHeader);
    const s = req.reduce((a,r)=>a+(up.includes(r)?1:0),0);
    if(s>score){score=s;best=i;} if(s>=req.length*0.8) return {idx:i,score:s};
  }
  return {idx:best,score};
}
function parseDateGuess(s){
  const t = strip(s);
  let d = new Date(t);
  if(isFinite(d)) return d;
  const p = t.split(/[\/\-]/);
  if(p.length===3){
    const cand = [`${p[0]}-${p[1]}-${p[2]}`,`${p[1]}-${p[0]}-${p[2]}`,`${p[2]}-${p[1]}-${p[0]}`];
    for(const c of cand){ const dd=new Date(c); if(isFinite(dd)) return dd; }
  }
  return new Date('Invalid');
}
const MES_ABR = ["ENE","FEB","MAR","ABR","MAY","JUN","JUL","AGO","SEP","OCT","NOV","DIC"];
function sortMesLabels(meses){
  return meses.sort((a,b)=>{
    const [ma,ya]=(a||"-").split("-");
    const [mb,yb]=(b||"-").split("-");
    const ia=MES_ABR.indexOf(ma);
    const ib=MES_ABR.indexOf(mb);
    const ya2=parseInt(ya,10)||0;
    const yb2=parseInt(yb,10)||0;
    if(ya2!==yb2) return ya2-yb2;
    return ia-ib;
  });
}
function canonicalSeason(value){
  const raw = strip(value||"")
    .replace(/–/g,'-')
    .replace(/—/g,'-')
    .replace(/\s+/g,'');
  const m = raw.match(/(\d{4})[^\d]?(\d{4})/);
  if(!m) return raw;
  return `${m[1]}-${m[2]}`;
}
function ingestCSV(raw){
  const delim = detectDelimiter(raw);
  const rows = parseCSV(raw, delim);
  const {idx} = findHeader(rows);
  if(idx<0) throw new Error("No se encontró encabezado dentro de las primeras 300 filas.");
  const header = rows[idx];
  const idxOf = name => header.findIndex(h => normalizeHeader(h).startsWith(name));
  const iORIG = idxOf("ORIGEN");
  const iFECHA = idxOf("FECHA");
  const iTEMP = idxOf("TEMPORADA");
  const iPRED = idxOf("PREDIO");
  const iSECT = idxOf("SECTOR");
  const iCUAR = idxOf("CUARTEL");
  const iFAEN = idxOf("FAENA");
  const iN1   = idxOf("NIVEL 1");
  const iN2   = idxOf("NIVEL 2");
  const iNT   = idxOf("NIVEL TRANSVERSAL");
  const iSUP  = header.findIndex(h=>normalizeHeader(h).startsWith("SUPERFICIE REAL"));
  const iTOT  = idxOf("TOTAL CUARTEL");
  const data = [];
  const supLast = {};
  let totalRows = 0;
  for(let r=idx+1;r<rows.length;r++){
    const row = rows[r]; if(!row || row.every(c=>!strip(c))) continue;
    const ORIGEN = iORIG>=0 ? strip(row[iORIG]||"") : "";
    if(ORIGEN && /PRESUP/i.test(ORIGEN)) continue;
    totalRows++;
    const FECHA = row[iFECHA]||"";
    const d = parseDateGuess(FECHA);
    const ok = isFinite(d);
    const TEMP = canonicalSeason(row[iTEMP]||"");
    const PREDIO = row[iPRED]||"";
    const SECTOR = row[iSECT]||"";
    const CUARTEL = row[iCUAR]||"";
    const FAENA = row[iFAEN]||"";
    const NIVEL_1 = row[iN1]||"";
    const NIVEL_2 = iN2>=0 ? (row[iN2]||"") : "";
    const NIVEL_TRANSVERSAL = iNT>=0 ? (row[iNT]||"") : "";
    const SUP = strip(row[iSUP]||"").replace(/\./g,'').replace(',','.');
    const SUPF = SUP? parseFloat(SUP): NaN;
    const TOT = strip(row[iTOT]||"");
    let VALOR;
    if(TOT.includes(",") && TOT.includes(".")) VALOR = parseFloat(TOT.replace(/\./g,'').replace(',','.'));
    else if(TOT.includes(",") && !TOT.includes(".")) VALOR = parseFloat(TOT.replace(',','.'));
    else VALOR = parseFloat(TOT);
    if(CUARTEL && !Number.isNaN(SUPF)) supLast[CUARTEL] = SUPF;
    if(TEMP==="2025-2026"){
      data.push({
        FECHA_STR: ok? d.toISOString().slice(0,10):"",
        MES_STR: ok? `${MES_ABR[d.getMonth()]}-${String(d.getFullYear()).slice(-2)}`:"",
        TEMPORADA: TEMP, PREDIO, SECTOR, CUARTEL, FAENA, NIVEL_1, NIVEL_2, NIVEL_TRANSVERSAL,
        VALOR: Number.isFinite(VALOR)? VALOR:0
      });
    }
  }
  return {rows:data, supMap:supLast, totalRows};
}
function ingestCSV2425(raw){
  const delim = detectDelimiter(raw);
  const rows = parseCSV(raw, delim);
  const {idx} = findHeader(rows);
  if(idx<0) throw new Error("No se encontró encabezado dentro de las primeras 300 filas (24-25).");
  const header = rows[idx];
  const idxOf = name => header.findIndex(h => normalizeHeader(h).startsWith(name));
  const iORIG = idxOf("ORIGEN");
  const iFECHA = idxOf("FECHA");
  const iTEMP = idxOf("TEMPORADA");
  const iPRED = idxOf("PREDIO");
  const iSECT = idxOf("SECTOR");
  const iCUAR = idxOf("CUARTEL");
  const iFAEN = idxOf("FAENA");
  const iN1   = idxOf("NIVEL 1");
  const iN2   = idxOf("NIVEL 2");
  const iNT   = idxOf("NIVEL TRANSVERSAL");
  const iSUP  = header.findIndex(h=>normalizeHeader(h).startsWith("SUPERFICIE REAL"));
  const iTOT  = idxOf("TOTAL CUARTEL");
  const data = [];
  const supLast = {};
  let totalRows = 0;
  for(let r=idx+1;r<rows.length;r++){
    const row = rows[r]; if(!row || row.every(c=>!strip(c))) continue;
    const ORIGEN = iORIG>=0 ? strip(row[iORIG]||"") : "";
    if(ORIGEN && /PRESUP/i.test(ORIGEN)) continue;
    totalRows++;
    const FECHA = row[iFECHA]||"";
    const d = parseDateGuess(FECHA);
    const ok = isFinite(d);
    const TEMP = canonicalSeason(row[iTEMP]||"");
    const PREDIO = row[iPRED]||"";
    const SECTOR = row[iSECT]||"";
    const CUARTEL = row[iCUAR]||"";
    const FAENA = row[iFAEN]||"";
    const NIVEL_1 = row[iN1]||"";
    const NIVEL_2 = iN2>=0 ? (row[iN2]||"") : "";
    const NIVEL_TRANSVERSAL = iNT>=0 ? (row[iNT]||"") : "";
    const SUP = strip(row[iSUP]||"").replace(/\./g,'').replace(',','.');
    const SUPF = SUP? parseFloat(SUP): NaN;
    const TOT = strip(row[iTOT]||"");
    let VALOR;
    if(TOT.includes(",") && TOT.includes(".")) VALOR = parseFloat(TOT.replace(/\./g,'').replace(',','.'));
    else if(TOT.includes(",") && !TOT.includes(".")) VALOR = parseFloat(TOT.replace(',','.'));
    else VALOR = parseFloat(TOT);
    if(CUARTEL && !Number.isNaN(SUPF)) supLast[CUARTEL] = SUPF;
    if(TEMP==="2024-2025"){
      data.push({
        FECHA_STR: ok? d.toISOString().slice(0,10):"",
        MES_STR: ok? `${MES_ABR[d.getMonth()]}-${String(d.getFullYear()).slice(-2)}`:"",
        TEMPORADA: TEMP, PREDIO, SECTOR, CUARTEL, FAENA, NIVEL_1, NIVEL_2, NIVEL_TRANSVERSAL,
        VALOR: Number.isFinite(VALOR)? VALOR:0
      });
    }
  }
  return {rows:data, supMap:supLast, totalRows};
}
let comp2425 = {rows:[], supMap:{}};
let comp2425Status = 'idle';
let comp2425ErrorReason = '';
let rentabilidadDataActual = [];
let rentabilidadData2425 = [];
let rentabilidadComparativoStatus = 'idle';
let rentabilidadData = [];
let rentabilidadStatus = 'idle';
let rentabilidadErrorReason = '';
const rentabilidadState = { cuartel: 'Todos', sortBy: 'resultado', sortDir: 'desc' };
const RENT_CARD_DEFAULTS = {
  total_ingresos: {label:'Total ingresos', enabled:1, order:10},
  total_costos: {label:'Total costos acumulados', enabled:1, order:20},
  resultado: {label:'Resultado', enabled:1, order:30},
  kilos_reales: {label:'Kilos reales', enabled:1, order:40},
  ingreso_hectarea: {label:'Ingreso por hectárea', enabled:1, order:50},
  costo_hectarea: {label:'Costo total por hectárea', enabled:1, order:60},
  ingresos_kilo: {label:'Ingresos por kilo', enabled:1, order:70},
  costo_kilo: {label:'Costo por kilo', enabled:1, order:80}
};
const HIDE_INV_STORAGE_KEY = 'dlh_hide_inv_2425';
const RESUMEN_STATUS_STORAGE_KEY = 'dlh_resumen_status_hidden';
const THEME_STORAGE_KEY = 'dlh_theme';
const SIDEBAR_COLLAPSED_STORAGE_KEY = 'dlh_sidebar_collapsed';
const FILTER_ACCORDIONS_STORAGE_KEY = 'dlh_filter_accordions';
let hideInv2425 = false;
let faenaOptionsCache = [];

const debugEnabled = (typeof dashboardHigueraData !== 'undefined' && !!dashboardHigueraData.debug);
try {
  hideInv2425 = localStorage.getItem(HIDE_INV_STORAGE_KEY) === '1';
} catch (e) {
  hideInv2425 = false;
}
function predioClas(row){
  const esInd = strip(row.PREDIO).toUpperCase()==="COSTOS INDIRECTOS" || strip(row.CUARTEL).toUpperCase()==="COSTOS INDIRECTOS";
  return esInd? "Indirectos":"Productivo";
}
const state = {
  data: [], supMap: {},
  filtros: {predio:"Todos", sector:"Todos", cuartel:"Todos", nivel1:"Todos", faena:"Todas", metrica:"VALOR", mes:"Todos", orden:"Desc"},
  detalle: {nivel1:"Todos", metrica:"VALOR", orden:"Desc"},
  comparativo: {ordenBy:"v25"}
};
const comparativoMeta = {
  source2425: 'REST csv 2024-25',
  source2526: 'API',
  rows2425Read: 0,
  rows2425Valid: 0,
  rows2526Read: 0,
  last2425Updated: (typeof dashboardHigueraData !== 'undefined' && dashboardHigueraData.last2425Updated)
    ? dashboardHigueraData.last2425Updated
    : '—',
  last2526Updated: '—',
  url2425: '—'
};
const comparativoOrdenLabels = {
  v24t: '24-25 total',
  v24m: '24-25 meses comparables',
  v25: '25-26 actual',
  diff: 'Diferencia (Δ)',
  pct: 'Diferencia %'
};
const FILTER_CHIP_META = {
  predio: { stateKey:'predio', clearValue:'Todos' },
  sector: { stateKey:'sector', clearValue:'Todos' },
  cuartel: { stateKey:'cuartel', clearValue:'Todos' },
  nivel1: { stateKey:'nivel1', clearValue:'Todos' },
  faena: { stateKey:'faena', clearValue:'Todas' },
  mes: { stateKey:'mes', clearValue:'Todos' }
};
const FILTER_CHIP_THRESHOLD = 12;
function escapeHtml(value){
  return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch] || ch));
}
function renderFilterChipGroup(container, options, selectedValues){
  if(!container) return;
  const selectedSet = new Set(selectedValues || []);
  container.innerHTML = options.map(opt=>{
    const active = selectedSet.has(opt.value);
    return `<button type="button" class="filter-choice-chip ${active?'is-active':''}" data-value="${escapeHtml(opt.value)}" aria-pressed="${active?'true':'false'}" data-selected="${active?'true':'false'}"><span>${escapeHtml(opt.label)}</span></button>`;
  }).join('');
}
function getSelectedCultivos(){
  if(state.filtros.sector === 'Todos') return [];
  return (Array.isArray(state.filtros.sector) ? state.filtros.sector : [state.filtros.sector])
    .filter(Boolean)
    .filter(s => s !== '__COSTOS_INDIRECTOS__');
}
function renderCuartelChips(rowsBase){
  const field = document.querySelector('.field-cuartel');
  const box = document.querySelector('#f_cuartel');
  const chipsWrap = document.querySelector('#f_cuartel_chips');
  if(!field || !box || !chipsWrap) return;
  const selectedCultivos = getSelectedCultivos();
  const shouldShow = selectedCultivos.length > 0;
  field.classList.toggle('hidden', !shouldShow);
  if(!shouldShow){
    state.filtros.cuartel = 'Todos';
    box.dataset.value = 'Todos';
    chipsWrap.innerHTML = '';
    return;
  }
  const availableRows = rowsBase.filter(r=>{
    const sector = strip(r.SECTOR||'') || 'Sin dato';
    return selectedCultivos.includes(sector);
  });
  const cuarteles = Array.from(new Set(availableRows.map(r=>strip(r.CUARTEL)||'—')))
    .filter(Boolean)
    .sort((a,b)=>a.localeCompare(b,'es'));
  const selected = state.filtros.cuartel === 'Todos'
    ? []
    : (Array.isArray(state.filtros.cuartel) ? state.filtros.cuartel : [state.filtros.cuartel]).filter(Boolean);
  const validSelected = selected.filter(c=>cuarteles.includes(c));
  if(selected.length !== validSelected.length){
    state.filtros.cuartel = validSelected.length ? validSelected : 'Todos';
  }
  const activeSelected = state.filtros.cuartel === 'Todos' ? [] : validSelected;
  const options = [{value:'Todos', label:'Todos'}].concat(cuarteles.map(c=>({value:c, label:c})));
  renderFilterChipGroup(chipsWrap, options, state.filtros.cuartel === 'Todos' ? ['Todos'] : activeSelected);
  box.dataset.value = state.filtros.cuartel === 'Todos' ? 'Todos' : activeSelected.join('|');
}
function formatStatusValue(value){
  const str = String(value || '').trim();
  return str ? str : '—';
}
function formatComparativoMeses(){
  const fm = state.filtros?.mes;
  if(fm==="Todos" || !fm) return 'Todos';
  if(Array.isArray(fm)) return fm.length ? fm.join(', ') : 'Ninguno';
  return String(fm);
}
function formatComparativoOrden(){
  const ordenBy = state.comparativo?.ordenBy || 'v25';
  const ordenLabel = comparativoOrdenLabels[ordenBy] || '25-26 actual';
  const dir = state.filtros?.orden === 'Asc' ? 'Ascendente' : 'Descendente';
  return `${ordenLabel} (${dir})`;
}
function buildStatusCardsHtml(){
  return `
    <div class="comp-status-grid">
      <div class="comp-status-card">
        <h4>24-25</h4>
        <div class="comp-status-row"><span>Fuente</span><strong>${formatStatusValue(comparativoMeta.source2425)}</strong></div>
        <div class="comp-status-row"><span>Filas leídas</span><strong>${fmt(comparativoMeta.rows2425Read)}</strong></div>
        <div class="comp-status-row"><span>Filas válidas</span><strong>${fmt(comparativoMeta.rows2425Valid)}</strong></div>
        <div class="comp-status-row"><span>Última actualización</span><strong>${formatStatusValue(comparativoMeta.last2425Updated)}</strong></div>
        <div class="comp-status-url"><span>URL final usada:</span> <code>${formatStatusValue(comparativoMeta.url2425)}</code></div>
      </div>
      <div class="comp-status-card">
        <h4>25-26</h4>
        <div class="comp-status-row"><span>Fuente</span><strong>${formatStatusValue(comparativoMeta.source2526)}</strong></div>
        <div class="comp-status-row"><span>Filas leídas</span><strong>${fmt(comparativoMeta.rows2526Read)}</strong></div>
        <div class="comp-status-row"><span>Última actualización</span><strong>${formatStatusValue(comparativoMeta.last2526Updated)}</strong></div>
      </div>
      <div class="comp-status-card">
        <h4>Filtros activos</h4>
        <div class="comp-status-row"><span>Meses seleccionados</span><strong>${formatComparativoMeses()}</strong></div>
        <div class="comp-status-row"><span>Orden comparativo</span><strong>${formatComparativoOrden()}</strong></div>
        <div class="comp-status-row"><span>Inversiones Varias</span><strong>${hideInv2425 ? 'Ocultas' : 'Mostradas'}</strong></div>
      </div>
    </div>
  `;
}
function updateComparativoStatus(){
  if(dashboardMetaSource) dashboardMetaSource.textContent = formatStatusValue(comparativoMeta.source2526);
  if(dashboardMetaGenerated) dashboardMetaGenerated.textContent = formatStatusValue(comparativoMeta.last2526Updated);
  const srcChip = document.getElementById('srcChip');
  if(srcChip) srcChip.textContent = formatStatusValue(comparativoMeta.source2526);
  const genInline = document.getElementById('gen');
  if(genInline) genInline.textContent = formatStatusValue(comparativoMeta.last2526Updated);
  const wrap = document.getElementById('comparativo-status');
  const statusCards = buildStatusCardsHtml();
  if(wrap){
    wrap.innerHTML = `<div class="comp-status-title">Estado de datos</div><div class="comp-status-body">${statusCards}</div>`;
  }
  const resumenWrap = document.querySelector('#resumen-status .comp-status-body');
  if(resumenWrap){
    resumenWrap.innerHTML = statusCards;
  }
}
function buildUrlWithTs(baseUrl, tsValue){
  const ts = String(tsValue || '').trim();
  const finalTs = ts ? ts : String(Date.now());
  try{
    const url = new URL(baseUrl, window.location.href);
    url.searchParams.set('ts', finalTs);
    return url.toString();
  }catch(e){
    const sep = baseUrl.includes('?') ? '&' : '?';
    return `${baseUrl}${sep}ts=${encodeURIComponent(finalTs)}`;
  }
}
function applyFilters(){
  let rows = state.data.slice();
  if(state.filtros.predio!=="Todos"){
    rows = rows.filter(r => (state.filtros.predio==="Solo Productivo"? predioClas(r)==="Productivo": predioClas(r)==="Indirectos"));
  }
  const selectedSectores = state.filtros.sector==="Todos"
    ? []
    : (Array.isArray(state.filtros.sector) ? state.filtros.sector : [state.filtros.sector]).filter(Boolean).filter(s => s !== '__COSTOS_INDIRECTOS__');
  if(selectedSectores.length){
    rows = rows.filter(r=>{
      const sector = strip(r.SECTOR||"") || "Sin dato";
      return selectedSectores.includes(sector);
    });
  }
  if(state.filtros.cuartel!=="Todos"){
    const cuarteles = Array.isArray(state.filtros.cuartel) ? state.filtros.cuartel : [state.filtros.cuartel];
    if(!cuarteles.length) return [];
    rows = rows.filter(r => cuarteles.includes(strip(r.CUARTEL||"") || "—"));
  }
  if(state.filtros.nivel1!=="Todos") rows = rows.filter(r => strip(r.NIVEL_1||"")===state.filtros.nivel1);
  if(state.filtros.faena!=="Todas") rows = rows.filter(r => strip(r.FAENA||"")===state.filtros.faena);
  if(hideInv2425) rows = rows.filter(r => !isInversionRow(r));
  if(state.filtros.mes!=="Todos"){
    const fm = state.filtros.mes;
    if(Array.isArray(fm) && fm.length){
      rows = rows.filter(r => fm.includes(r.MES_STR));
    }else if(Array.isArray(fm) && !fm.length){
      rows = [];
    }else if(fm && fm!=="Todos"){
      rows = rows.filter(r => r.MES_STR===fm);
    }
  }
  return rows;
}
function sumBy(arr, keyFn){
  const map = new Map();
  for(const r of arr){ const k = keyFn(r); map.set(k, (map.get(k)||0) + (r.VALOR||0)); }
  return map;
}
function metricByKey(rows, keyAccessor){
  if(state.filtros.metrica==="VALOR"){
    const m = sumBy(rows, keyAccessor);
    return Array.from(m.entries()).map(([k,v])=>({k,v}));
  }else{
    const bucket = new Map();
    for(const r of rows){
      const k = keyAccessor(r);
      if(!bucket.has(k)) bucket.set(k, {sum:0, cuSet:new Set()});
      const b = bucket.get(k);
      b.sum += (r.VALOR||0);
      if(r.CUARTEL) b.cuSet.add(r.CUARTEL);
    }
    const arr=[];
    for(const [k,{sum,cuSet}] of bucket.entries()){
      const sup = Array.from(cuSet).reduce((a,c)=> a + (state.supMap[c]||0), 0);
      arr.push({k, v: (sup>0? sum/sup : NaN)});
    }
    return arr;
  }
}
function renderFaenaOptions(){
  const select = document.querySelector("#f_faena");
  if(!select) return;
  let options = faenaOptionsCache.slice();
  if(state.filtros.faena !== 'Todas' && !options.some(o => o.k === state.filtros.faena)){
    const selected = faenaOptionsCache.find(o => o.k === state.filtros.faena);
    if(selected) options = [selected].concat(options);
  }
  select.innerHTML = `<option${state.filtros.faena === 'Todas' ? ' selected' : ''}>Todas</option>` + options.map(o=>`<option${o.k===state.filtros.faena?' selected':''}>${o.k} — ${fmt(o.v)}</option>`).join('');
  if(!options.length){
    select.innerHTML += '<option disabled>Sin coincidencias</option>';
  }
}
function setupFaenaSearch(){
  return;
}
function renderPredioChips(){
  const wrap = document.querySelector('#f_predio_chips');
  if(!wrap) return;
  const options = [
    {value:'Todos', label:'Todos'},
    {value:'Solo Productivo', label:'Solo Productivo'},
    {value:'Solo Costos Indirectos', label:'Solo Costos Indirectos'}
  ];
  renderFilterChipGroup(wrap, options, [state.filtros.predio]);
}
function renderCategoriaControl(arrN1){
  const select = document.querySelector('#f_n1');
  const chips = document.querySelector('#f_n1_chips');
  if(!select || !chips) return;
  const useChips = arrN1.length > 0 && arrN1.length <= FILTER_CHIP_THRESHOLD;
  if(useChips){
    select.classList.add('hidden');
    chips.classList.remove('hidden');
    const options = [{value:'Todos', label:'Todos'}].concat(arrN1.map(o=>({value:o.k, label:o.k})));
    renderFilterChipGroup(chips, options, [state.filtros.nivel1]);
  }else{
    select.classList.remove('hidden');
    chips.classList.add('hidden');
    chips.innerHTML = '';
  }
}
function populateCombos(){
  const rowsBase = state.data.filter(r=> (state.filtros.predio==="Todos")? true : (state.filtros.predio==="Solo Productivo" ? predioClas(r)==="Productivo" : predioClas(r)==="Indirectos"));
  const sectores = Array.from(new Set(rowsBase.map(r=>strip(r.SECTOR)||"Sin dato"))).sort((a,b)=>a.localeCompare(b,'es'));
  renderPredioChips();
  const selectedRaw = state.filtros.sector==="Todos" ? [] : (Array.isArray(state.filtros.sector) ? state.filtros.sector.slice() : [state.filtros.sector]);
  const selectedSectores = selectedRaw.filter(s => s !== '__COSTOS_INDIRECTOS__');
  if(state.filtros.predio === 'Solo Productivo' && selectedSectores.length !== selectedRaw.length){
    state.filtros.sector = selectedSectores.length ? selectedSectores : 'Todos';
    if(state.filtros.sector === 'Todos') state.filtros.cuartel = 'Todos';
  }
  document.querySelector("#f_cultivo").innerHTML = `<option>Todos</option>` + sectores.map(s=>`<option${s===state.filtros.sector?' selected':''}>${s}</option>`).join('');
  const cultivoWrap = document.querySelector('#f_cultivo_chips');
  if(cultivoWrap){
    const priority = ['CEREZOS','OTROS CULTIVOS','VIÑA'];
    const byUpper = new Map(sectores.map(s=>[String(s||'').toUpperCase(), s]));
    const orderedSectores = [
      ...priority.filter(k=>byUpper.has(k)).map(k=>byUpper.get(k)),
      ...sectores.filter(s=>{
        const upper = String(s||'').toUpperCase();
        return upper !== 'COSTOS INDIRECTOS' && !priority.includes(upper);
      })
    ];
    const chips = [
      {k:'Todos', label:'Todos', icon:'🌱'},
      ...orderedSectores.map(s=>({k:s, label:s, icon:/cerez/i.test(s)?'🍒':(/[vV][ií]?[ñn]a/i.test(s)?'🍇':'🌿')})),
      ...(state.filtros.predio === 'Solo Productivo' ? [] : [{k:'__COSTOS_INDIRECTOS__', label:'Costos indirectos', icon:'🏷️', multiline:true}])
    ];
    cultivoWrap.innerHTML = chips.map(ch=>{
      const active = ch.k==='Todos'
        ? selectedSectores.length===0 && state.filtros.predio!=="Solo Costos Indirectos"
        : (ch.k==='__COSTOS_INDIRECTOS__' ? state.filtros.predio==="Solo Costos Indirectos" : selectedSectores.includes(ch.k));
      const multiline = ch.multiline || /otros cultivos/i.test(ch.label) || /costos indirectos/i.test(ch.label);
      return `<button type="button" class="cultivo-chip ${active?'is-active':''} ${multiline?'is-compact':''}" data-cultivo="${ch.k}" data-selected="${active?'true':'false'}" aria-pressed="${active?'true':'false'}"><span>${ch.icon}</span><span class="${multiline?'multi':''}">${ch.label}</span></button>`;
    }).join('');
  }
  const n1Rows = rowsBase.filter(r=>{
    const fm = state.filtros.mes;
    if(fm==="Todos") return true;
    if(Array.isArray(fm) && fm.length) return fm.includes(r.MES_STR);
    return r.MES_STR===fm;
  });
  const arrN1 = metricByKey(n1Rows, r=>strip(r.NIVEL_1)||"—").sort((a,b)=> (state.filtros.orden==="Desc"? (b.v-a.v):(a.v-b.v)));
  document.querySelector("#f_n1").innerHTML = `<option>Todos</option>` + arrN1.map(o=>`<option${o.k===state.filtros.nivel1?' selected':''}>${o.k} — ${fmt(o.v)}</option>`).join('');
  renderCategoriaControl(arrN1);
  const arrFa = metricByKey(n1Rows, r=>strip(r.FAENA)||"—").sort((a,b)=> (state.filtros.orden==="Desc"? (b.v-a.v):(a.v-b.v)));
  faenaOptionsCache = arrFa.slice();
  renderFaenaOptions();
  const cuarteles = Array.from(new Set(n1Rows.map(r=>strip(r.CUARTEL)||"—"))).filter(Boolean).sort((a,b)=>a.localeCompare(b,'es'));
  const cuartelBox = document.querySelector('#f_cuartel');
  if(cuartelBox){
    const panel = cuartelBox.querySelector('.mes-dropdown-panel');
    const labelBtnCu = cuartelBox.querySelector('.mes-label');
    if(panel && labelBtnCu){
      const existingSearch = panel.querySelector('#cuartel_search');
      const term = strip(existingSearch?.value || '').toLowerCase();
      const filtered = !term ? cuarteles : cuarteles.filter(c=>c.toLowerCase().includes(term));
      const selected = state.filtros.cuartel==="Todos" ? [] : (Array.isArray(state.filtros.cuartel) ? state.filtros.cuartel : [state.filtros.cuartel]);
      panel.innerHTML = `<div class="mes-dropdown-item todos"><input type="checkbox" id="cuartel_todos" ${state.filtros.cuartel==="Todos"?'checked':''}><label for="cuartel_todos">Seleccionar todos</label></div>
        <div class="mes-dropdown-item"><input type="search" id="cuartel_search" placeholder="Buscar cuartel" value="${term}"></div>` +
        filtered.map(c=>`<div class="mes-dropdown-item"><input type="checkbox" id="cuartel_${c.replace(/[^a-zA-Z0-9_-]/g,'_')}" data-cuartel="${c}" ${state.filtros.cuartel==="Todos" || selected.includes(c)?'checked':''}><label for="cuartel_${c.replace(/[^a-zA-Z0-9_-]/g,'_')}">${c}</label></div>`).join('');
      if(state.filtros.cuartel==="Todos") labelBtnCu.textContent = 'Todos';
      else labelBtnCu.innerHTML = `${selected.length} cuarteles <span class="mes-count">${selected.length}</span>`;
    }
  }
  renderCuartelChips(rowsBase);
  
  const meses = Array.from(new Set(state.data.map(r=>r.MES_STR))).filter(Boolean);
  const orderMes = (a,b)=>{ const [ma,ya]=a.split('-'); const [mb,yb]=b.split('-'); const ia=MES_ABR.indexOf(ma); const ib=MES_ABR.indexOf(mb); const ya2=parseInt(ya,10)||0; const yb2=parseInt(yb,10)||0; if(ya2!==yb2) return ya2-yb2; return ia-ib; };
  meses.sort(orderMes);
  const selMes = state.filtros.mes;
  const selArr = Array.isArray(selMes) ? selMes : (selMes && selMes!=="Todos" ? [selMes] : []);
  const allSelected = selMes==="Todos" || selArr.length===0;
  const container = document.querySelector("#f_mes");
  const panel = container.querySelector('.mes-dropdown-panel');
  const labelBtn = container.querySelector('.mes-label');
  
  // Generar checkboxes
  panel.innerHTML = `<div class="mes-dropdown-item todos"><input type="checkbox" id="mes_todos" ${allSelected?'checked':''}><label for="mes_todos">Seleccionar todos</label></div><div class="mes-dropdown-item"><input type="checkbox" id="mes_none" ${Array.isArray(selMes)&&!selMes.length?'checked':''}><label for="mes_none">Ninguno</label></div>` +
    meses.map(m=>`<div class="mes-dropdown-item"><input type="checkbox" id="mes_${m}" data-mes="${m}" ${allSelected || selArr.includes(m)?'checked':''}><label for="mes_${m}">${m}</label></div>`).join('');
  
  // Actualizar label del botón
  if(allSelected){
    labelBtn.innerHTML = 'Todos';
  }else if(Array.isArray(selMes) && !selMes.length){
    labelBtn.innerHTML = 'Ninguno';
  }else if(selArr.length===1){
    labelBtn.innerHTML = selArr[0];
  }else if(selArr.length<=2){
    const extras = selArr.length - 2;
    labelBtn.innerHTML = extras>0 ? `${selArr.slice(0,2).join(', ')} +${extras}` : selArr.join(', ');
  }else if(selArr.length<=4){
    labelBtn.innerHTML = `${selArr.slice(0,2).join(', ')} +${selArr.length-2}`;
  }else{
    labelBtn.innerHTML = `${selArr.length} meses seleccionados <span class="mes-count">${selArr.length}</span>`;
  }
}
function buildResumen(){
  const rows = applyFilters();
  const cuarteles = Array.from(new Set(rows.map(r=>r.CUARTEL))).filter(Boolean);
  
  let meses;
  if(state.filtros.mes==="Todos" || (Array.isArray(state.filtros.mes) && !state.filtros.mes.length)){
    meses = Array.from(new Set(rows.map(r=>r.MES_STR))).filter(Boolean);
  }else if(Array.isArray(state.filtros.mes)){
    meses = state.filtros.mes.slice();
  }else{
    meses = [state.filtros.mes];
  }
  const orderMes = (a,b)=>{ const [ma,ya]=a.split('-'); const [mb,yb]=b.split('-'); const ia = MES_ABR.indexOf(ma); const ib = MES_ABR.indexOf(mb); const ya2 = parseInt(ya,10); const yb2 = parseInt(yb,10); if(ya2!==yb2) return ya2-yb2; return ia-ib; };
  meses.sort(orderMes);
  const supMap = state.supMap;
  const totalFiltrado = rows.reduce((a,r)=>a+(r.VALOR||0),0) || 0;
  const per = {}; for(const r of rows){ const c=r.CUARTEL||"—"; const m=r.MES_STR||"—"; per[c]=per[c]||{}; per[c][m]=(per[c][m]||0)+(r.VALOR||0); }
  function metricCuartelTotal(c){
    const s = meses.reduce((a,m)=>a+(per[c][m]||0),0);
    if(state.filtros.metrica==="VALOR") return s;
    const sup=supMap[c]; if(!sup||sup<=0) return NaN; return s/sup;
  }
  const sorted = cuarteles.sort((a,b)=>{ const va=metricCuartelTotal(a), vb=metricCuartelTotal(b); return state.filtros.orden==="Desc"?(vb-va):(va-vb); });
  const tbl = document.createElement('table');
  tbl.className = 'dense-table resumen-table resumen-table--premium';
  let thead = `<thead><tr><th>Cuartel</th>`; for(const m of meses) thead+=`<th>${m}</th>`; thead+=`<th>Total</th></tr></thead>`;
  tbl.innerHTML = thead + `<tbody></tbody><tfoot></tfoot>`; const tb = tbl.querySelector('tbody');
  sorted.forEach((c,index)=>{
    const vals = meses.map(m=> per[c]?.[m] || 0);
    const totalMes = vals.reduce((a,b)=>a+b,0);
    let metricVals = vals.slice();
    let metricTotal = totalMes;
    if(state.filtros.metrica==="COSTO_HA"){
      const sup = supMap[c]; metricVals = metricVals.map(v=> (sup? v/sup : NaN)); metricTotal = (sup? totalMes/sup : NaN);
    }
    const pct = totalFiltrado>0 ? (totalMes/totalFiltrado) : 0;
    const tr = document.createElement('tr');
    const rank = `<span class="summary-rank">${index + 1}</span>`;
    const btn = `<div class="summary-rowhead">${rank}<button class="cuartel-btn" data-c="${c}">${c}</button><span class="pct">${pctFmt(pct)}</span></div>`;
    let tds = `<td>${btn}</td>`; for(const v of metricVals) tds+=`<td>${fmt(v)}</td>`;
    const totalCell = (state.filtros.mes!=="Todos" && !Array.isArray(state.filtros.mes)) ? metricVals[0] : metricTotal;
    tds += `<td class="summary-total-cell">${fmt(totalCell)}</td>`; tr.innerHTML = tds; tb.appendChild(tr);
    const trd = document.createElement('tr'); trd.className="hidden mini"; const cols = meses.length + 2; trd.innerHTML = `<td colspan="${cols}"><div class="mini-wrap"></div></td>`; tb.appendChild(trd);
  });
  const tf = tbl.querySelector('tfoot'); let totRow = `<tr><td><div class="summary-rowhead summary-rowhead--total"><span class="summary-rank summary-rank--total">Σ</span><strong>Totales</strong></div></td>`;
  const colTotals = meses.map(m=> rows.filter(r=>r.MES_STR===m).reduce((a,r)=>a+(r.VALOR||0),0));
  let colMetrics = colTotals.slice();
  if(state.filtros.metrica==="COSTO_HA"){
    const cuSet = new Set(rows.map(r=>r.CUARTEL).filter(Boolean)); const sumSup = Array.from(cuSet).reduce((a,c)=> a + (state.supMap[c]||0), 0);
    colMetrics = colTotals.map(v=> sumSup>0? v/sumSup : NaN);
  }
  for(const v of colMetrics) totRow += `<td>${fmt(v)}</td>`;
  const totalAll = colTotals.reduce((a,b)=>a+b,0);
  const totalAllMetric = (state.filtros.metrica==="COSTO_HA") ? (()=>{ const cuSet = new Set(rows.map(r=>r.CUARTEL).filter(Boolean)); const sumSup = Array.from(cuSet).reduce((a,c)=>a+(state.supMap[c]||0),0); return sumSup>0? totalAll/sumSup : NaN; })() : totalAll;
  const totalCell = (state.filtros.mes!=="Todos" && !Array.isArray(state.filtros.mes)) ? (state.filtros.metrica==="COSTO_HA" ? colMetrics[0] : colTotals[0]) : totalAllMetric;
  totRow += `<td class="summary-total-cell">${fmt(totalCell)}</td></tr>`; tf.innerHTML = totRow;
  const metricLabel = state.filtros.metrica === 'COSTO_HA' ? 'Costo por hectárea' : 'Gasto total';
  const mesesLabel = meses.length ? `${meses.length} ${meses.length === 1 ? 'mes' : 'meses'}` : 'Sin meses';
  const cont = document.querySelector("#resumen");
  cont.innerHTML = `<div class="summary-table-shell"><div class="summary-table-head"><div><span class="summary-table-kicker">Tabla principal</span><strong>Lectura por cuartel</strong></div><div class="summary-table-meta"><span class="summary-pill">${metricLabel}</span><span class="summary-pill">${mesesLabel}</span><span class="summary-pill">${sorted.length} cuarteles</span></div></div><div class="summary-table-body"></div></div>`;
  cont.querySelector('.summary-table-body').appendChild(tbl);
  cont.querySelectorAll(".cuartel-btn").forEach(btn=>{
    btn.onclick = ()=>{
      const c = btn.dataset.c;
      const trd = btn.closest('tr').nextElementSibling;
      const wrap = trd.querySelector('.mini-wrap');
      if(state.filtros.nivel1!=="Todos"){ trd.classList.add('hidden'); return; }
      if(!trd.classList.contains('hidden')){ trd.classList.add('hidden'); return; }
      const rows = applyFilters();
      const rcu = rows.filter(r=>r.CUARTEL===c);
      const byN1M = {}; for(const r of rcu){ const n=strip(r.NIVEL_1)||"—"; const m=r.MES_STR||"—"; byN1M[n]=byN1M[n]||{}; byN1M[n][m]=(byN1M[n][m]||0)+(r.VALOR||0); }
      let mesesMini;
      if(state.filtros.mes==="Todos" || (Array.isArray(state.filtros.mes) && !state.filtros.mes.length)){
        mesesMini = Array.from(new Set(rcu.map(r=>r.MES_STR))).filter(Boolean);
      }else if(Array.isArray(state.filtros.mes)){
        mesesMini = state.filtros.mes.slice();
      }else{
        mesesMini = [state.filtros.mes];
      }
      const orderMesMini = (a,b)=>{
        const [ma,ya] = (a||"").split('-');
        const [mb,yb] = (b||"").split('-');
        const ia = MES_ABR.indexOf(ma);
        const ib = MES_ABR.indexOf(mb);
        const ya2 = parseInt(ya,10)||0;
        const yb2 = parseInt(yb,10)||0;
        if(ya2!==yb2) return ya2-yb2;
        return ia-ib;
      };
      mesesMini.sort(orderMesMini);
      const n1s = Object.keys(byN1M).sort((a,b)=>{ const ta=Object.values(byN1M[a]).reduce((A,B)=>A+B,0); const tb=Object.values(byN1M[b]).reduce((A,B)=>A+B,0); return tb-ta; });
      const tbl2 = document.createElement('table'); tbl2.className = 'dense-table resumen-mini-table'; let th = `<thead><tr><th>NIVEL 1</th>`; for(const m of mesesMini) th+=`<th>${m}</th>`; th+=`<th>TOTAL</th></tr></thead>`; tbl2.innerHTML = th + `<tbody></tbody>`; const tb2 = tbl2.querySelector('tbody');
      for(const n of n1s){
        let tds = `<td>${n}</td>`; let tot=0;
        for(const m of mesesMini){ const v=byN1M[n][m]||0; tot+=v; const vv=(state.filtros.metrica==="COSTO_HA")?(state.supMap[c]? v/state.supMap[c] : NaN):v; tds+=`<td>${fmt(vv)}</td>`; }
        const tMetric = (state.filtros.metrica==="COSTO_HA")?(state.supMap[c]? tot/state.supMap[c]:NaN):tot;
        
        const totShown = (!Array.isArray(state.filtros.mes) && state.filtros.mes!=="Todos")
          ? ((state.filtros.metrica==="COSTO_HA")?(state.supMap[c]? (byN1M[n][mesesMini[0]]||0)/state.supMap[c]:NaN):(byN1M[n][mesesMini[0]]||0))
          : tMetric;
        tds+=`<td>${fmt(totShown)}</td>`; const tr=document.createElement('tr'); tr.innerHTML=tds; tb2.appendChild(tr);
      }
      wrap.innerHTML = ""; wrap.appendChild(tbl2); trd.classList.remove('hidden');
    };
  });
  document.querySelector("#nota-resumen").classList.remove('hidden');
}
function buildCharts(){
  const rows = applyFilters();
  const chipColor = '#60a5fa';
  const byC = new Map();
  for(const r of rows){ const k=r.CUARTEL||"—"; const prev = byC.get(k)||{sum:0, cu:k}; prev.sum += (r.VALOR||0); byC.set(k, prev); }
  let data = Array.from(byC.entries()).map(([k,obj])=>{
    if(state.filtros.metrica==='VALOR') return {name:k, value:obj.sum};
    const sup = state.supMap[k]||0; return {name:k, value: sup>0 ? obj.sum/sup : 0};
  });
  data.sort((a,b)=> state.filtros.orden==="Desc" ? b.value-a.value : a.value-b.value);
  const pad=40, bw=36, gap=12;
  const w = Math.max(900, pad*2 + data.length*(bw+gap));
  const h = 420;
  const max = Math.max(1, ...data.map(d=>d.value));
  const ticks = 5;
  let svg = `<svg width="${w}" height="${h}" xmlns="http://www.w3.org/2000/svg">`;
  for(let i=0;i<=ticks;i++){
    const y = h - pad - Math.round((h-2*pad)*i/ticks);
    svg += `<line x1="${pad}" y1="${y}" x2="${w-pad}" y2="${y}" stroke="#1f2433" stroke-width="1"/>`;
    const tVal = Math.round(max*i/ticks);
    svg += `<text x="${pad-6}" y="${y+4}" font-size="11" fill="#8b93a6" text-anchor="end">${fmt(tVal)}</text>`;
  }
  let x=pad;
  for(const d of data){
    const bh = Math.round((h-2*pad)*d.value/max);
    const y = h - pad - bh;
    svg += `<rect x="${x}" y="${y}" width="${bw}" height="${bh}" rx="6" fill="${chipColor}"/>`;
    svg += `<text x="${x+bw/2}" y="${y-6}" font-size="11" fill="#e6e9f2" text-anchor="middle">${fmt(d.value)}</text>`;
    svg += `<text transform="translate(${x+bw/2},${h-pad+12}) rotate(45)" font-size="10" fill="#8b93a6" text-anchor="start">${d.name}</text>`;
    x += bw+gap;
  }
  svg += `</svg>`;
  document.querySelector("#chart_bars").innerHTML = `<div class="chart-scroll">${svg}</div>`;
  const sorted = data.slice();
  const top = sorted.slice(0,12);
  const rest = sorted.slice(12).reduce((a,x)=>a+x.value,0);
  if(rest>0) top.push({name:"Otros", value:rest});
  const total = top.reduce((a,b)=>a+b.value,0) || 1;
  let acc=0; const cx=220,cy=190,r=140;
  function arc(cx, cy, r, start, end){
    const x1=cx+r*Math.cos(start), y1=cy+r*Math.sin(start);
    const x2=cx+r*Math.cos(end),   y2=cy+r*Math.sin(end);
    const large=(end-start)>Math.PI?1:0;
    return `M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 ${large} 1 ${x2} ${y2} Z`;
  }
  const colors = ["#60a5fa","#34d399","#fbbf24","#f472b6","#a78bfa","#f87171","#22d3ee","#c084fc","#fb7185","#f59e0b","#4ade80","#93c5fd","#e5e7eb"];
  let pieces = ``; let legend = ``;
  for(let i=0;i<top.length;i++){
    const d=top[i]; const frac=d.value/total; const a0=acc*2*Math.PI; const a1=(acc+frac)*2*Math.PI; acc+=frac;
    pieces += `<path d="${arc(cx,cy,r,a0,a1)}" fill="${colors[i%colors.length]}" stroke="#0b0d12" stroke-width="1"/>`;
    legend += `<div class="row small"><span style="display:inline-block;width:10px;height:10px;background:${colors[i%colors.length]};border-radius:2px;margin-right:6px"></span>${d.name}: ${pctFmt(frac)} (${fmt(d.value)})</div>`;
  }
  const pie = `<svg width="480" height="380">${pieces}</svg>`;
  document.querySelector("#chart_pie").innerHTML = `<div class="row">${pie}<div style="margin-left:18px">${legend}</div></div>`;
}
function buildDetalle(){
  const base = state.data.slice().filter(r=>{
    if(state.filtros.predio!=="Todos"){
      const cl=predioClas(r);
      if(state.filtros.predio==="Solo Productivo" && cl!=="Productivo") return false;
      if(state.filtros.predio==="Solo Costos Indirectos" && cl!=="Indirectos") return false;
    }
    const sectorFiltro = state.filtros.sector;
    if(sectorFiltro!=="Todos"){
      const sectorRow = strip(r.SECTOR||"");
      const sectores = (Array.isArray(sectorFiltro) ? sectorFiltro : [sectorFiltro])
        .filter(Boolean)
        .filter(s => s !== '__COSTOS_INDIRECTOS__');
      if(sectores.length && !sectores.includes(sectorRow)) return false;
    }
    if(state.filtros.mes!=="Todos"){
      const fm = state.filtros.mes;
      if(Array.isArray(fm) && fm.length){
        if(!fm.includes(r.MES_STR)) return false;
      }else if(fm && fm!=="Todos"){
        if(r.MES_STR!==fm) return false;
      }
    }
    if(hideInv2425 && isInversionRow(r)) return false;
    return true;
  });
  const rows = (state.detalle.nivel1==="Todos")? base : base.filter(r=>strip(r.NIVEL_1||"")===state.detalle.nivel1);
  const cuarteles = Array.from(new Set(rows.map(r=>r.CUARTEL))).filter(Boolean);
  const cont = document.querySelector("#detalle"); cont.innerHTML = "";
  const n1s = Array.from(new Set(base.map(r=>strip(r.NIVEL_1)||"—"))).sort((a,b)=>a.localeCompare(b,'es'));
  document.querySelector("#d_n1").innerHTML = `<option>Todos</option>` + n1s.map(n=>`<option${n===state.detalle.nivel1?' selected':''}>${n}</option>`).join('');
  for(const c of cuarteles){
    const rcu = rows.filter(r=>r.CUARTEL===c);
    const byFaMes = {};
    for(const r of rcu){
      const f=strip(r.FAENA)||"—";
      const m=r.MES_STR||"—";
      byFaMes[f]=byFaMes[f]||{};
      byFaMes[f][m]=(byFaMes[f][m]||0)+(r.VALOR||0);
    }
    const faenas = Object.keys(byFaMes).sort((a,b)=>{
      const ta=Object.values(byFaMes[a]).reduce((A,B)=>A+B,0);
      const tb=Object.values(byFaMes[b]).reduce((A,B)=>A+B,0);
      return state.detalle.orden==="Desc" ? (tb-ta) : (ta-tb);
    });
    const box = document.createElement('div'); box.className="card detail-card";
    const head=document.createElement('div');
    head.className="acc-head";
    head.innerHTML=`<div class="acc-title"><span class="acc-caret">▶</span><b>${c}</b></div><span class="badge">${fmt(faenas.length)} faenas</span>`;
    const body=document.createElement('div'); body.className="acc-body hidden";

    const table=document.createElement('table');
    table.className = 'dense-table detalle-table';
    let meses;
    if(state.filtros.mes==="Todos" || (Array.isArray(state.filtros.mes) && !state.filtros.mes.length)){
      meses = Array.from(new Set(rcu.map(r=>r.MES_STR))).filter(Boolean);
    }else if(Array.isArray(state.filtros.mes)){
      meses = state.filtros.mes.slice();
    }else{
      meses = [state.filtros.mes];
    }
    const orderMes = (a,b)=>{ const [ma,ya]=a.split('-'); const [mb,yb]=b.split('-'); const ia=MES_ABR.indexOf(ma); const ib=MES_ABR.indexOf(mb); const ya2=parseInt(ya,10)||0; const yb2=parseInt(yb,10)||0; if(ya2!==yb2) return ya2-yb2; return ia-ib; };
    meses.sort(orderMes);
    let th=`<thead><tr><th>FAENA</th>`;
    for(const m of meses) th+=`<th>${m}</th>`;
    th+=`<th>TOTAL</th></tr></thead><tbody></tbody>`;
    table.innerHTML=th;
    const tb=table.querySelector('tbody');
    for(const f of faenas){
      let row=`<td>${f}</td>`;
      let tot=0;
      for(const m of meses){
        const v=byFaMes[f][m]||0;
        tot+=v;
        const val=(state.detalle.metrica==="COSTO_HA")?(state.supMap[c]? v/state.supMap[c]:NaN):v;
        row+=`<td>${fmt(val)}</td>`;
      }
      const totVal=(state.detalle.metrica==="COSTO_HA")?(state.supMap[c]? tot/state.supMap[c]:NaN):tot;
      row+=`<td>${fmt(totVal)}</td>`;
      const tr=document.createElement('tr');
      tr.innerHTML=row;
      tb.appendChild(tr);
    }
    const tableWrap = document.createElement('div');
    tableWrap.className = 'table-wrap';
    tableWrap.appendChild(table);
    body.appendChild(tableWrap);
    box.appendChild(head);
    box.appendChild(body);
    cont.appendChild(box);
    head.onclick = ()=>{
      const wasHidden = body.classList.contains('hidden');
      body.classList.toggle('hidden');
      head.querySelector('.acc-caret').textContent = wasHidden ? '▼' : '▶';
    };
  }
  const exp = document.querySelector("#d_expand"), col = document.querySelector("#d_collapse");
  if(exp){ exp.onclick = ()=> { Array.from(document.querySelectorAll('#detalle .acc-body')).forEach(b=>b.classList.remove('hidden')); Array.from(document.querySelectorAll('#detalle .acc-head .acc-caret')).forEach(c=>c.textContent='▼'); } }
  if(col){ col.onclick = ()=> { Array.from(document.querySelectorAll('#detalle .acc-body')).forEach(b=>b.classList.add('hidden')); Array.from(document.querySelectorAll('#detalle .acc-head .acc-caret')).forEach(c=>c.textContent='▶'); } }
}
function buildComparativo(){
  const filtros = state.filtros;
  const tbody = document.querySelector('#tabla-comparativo tbody');
  const tfoot = document.querySelector('#tabla-comparativo tfoot');
  if(!tbody || !tfoot) return;
  updateComparativoStatus();
  if(!state.data || !state.data.length){
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--muted)">No hay datos 25-26 cargados (API/fallback). Revisa conexión o URL de API en ajustes.</td></tr>`;
    tfoot.innerHTML = "";
    return;
  }
  if(comp2425Status==='idle' || comp2425Status==='loading'){
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--muted)">Cargando temporada 24-25…</td></tr>`;
    tfoot.innerHTML = "";
    ensureComparativo2425Data().then(()=> buildComparativo());
    return;
  }
  if(!comp2425 || !comp2425.rows || !comp2425.rows.length){
    const reason = comp2425ErrorReason || 'Sin datos temporada 24-25 para comparar.';
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--muted)">${reason}</td></tr>`;
    tfoot.innerHTML = "";
    return;
  }
  function applyFiltersComparativo(rows, incluirMes){
    let out = rows.slice();
    if(filtros.predio!=="Todos"){
      out = out.filter(r => (filtros.predio==="Solo Productivo"
        ? predioClas(r)==="Productivo"
        : predioClas(r)==="Indirectos"));
    }
    if(filtros.sector!=="Todos"){
      const sectores = (Array.isArray(filtros.sector) ? filtros.sector : [filtros.sector])
        .filter(Boolean)
        .filter(s => s !== '__COSTOS_INDIRECTOS__');
      if(sectores.length){
        const sectorSet = new Set(sectores.map(s => strip(s)));
        out = out.filter(r => sectorSet.has(strip(r.SECTOR||"")));
      }
    }
    if(filtros.nivel1!=="Todos") out = out.filter(r => strip(r.NIVEL_1||"")===filtros.nivel1);
    if(filtros.faena!=="Todas") out = out.filter(r => strip(r.FAENA||"")===filtros.faena);
    if(hideInv2425){
      out = out.filter(r => !isInversionRow(r));
    }
    
    if(incluirMes && filtros.mes!=="Todos"){
      const fm = filtros.mes;
      let codes = [];
      if(Array.isArray(fm) && fm.length){
        codes = fm.map(x => (x||"").slice(0,3));
      }else if(fm && fm!=="Todos"){
        codes = [(fm||"").slice(0,3)];
      }
      if(codes.length){
        const codeSet = new Set(codes);
        out = out.filter(r => codeSet.has((r.MES_STR||"").slice(0,3)));
      }
    }
    return out;
  }
  // Comparativo usa el filtro global de meses del header
  let rows25 = applyFiltersComparativo(state.data, true);
  let rows24Full = applyFiltersComparativo(comp2425.rows, false);
  let rows24Match = applyFiltersComparativo(comp2425.rows, true);
  if(filtros.mes==="Todos"){
    const meses25 = new Set(rows25.map(r => (r.MES_STR||"").slice(0,3)).filter(Boolean));
    if(meses25.size){
      rows24Match = rows24Match.filter(r => meses25.has((r.MES_STR||"").slice(0,3)));
    }
  }
  function agruparPorCuartel(rows){
    const agg = {};
    for(const r of rows){
      const c = strip(r.CUARTEL||"");
      if(!c) continue;
      agg[c] = (agg[c]||0) + (r.VALOR||0);
    }
    return agg;
  }
  const agg25       = agruparPorCuartel(rows25);
  const agg24Full   = agruparPorCuartel(rows24Full);
  const agg24Match  = agruparPorCuartel(rows24Match);
  const cuSet = new Set([...Object.keys(agg25), ...Object.keys(agg24Full), ...Object.keys(agg24Match)]);
  const cuarteles = Array.from(cuSet).filter(Boolean);
  const metrica = (filtros.metrica==="COSTO_HA") ? "COSTO_HA" : "VALOR";
  const sup25 = state.supMap || {};
  const sup24 = comp2425.supMap || {};
  const rowsComp = cuarteles.map(c => {
    const tot25      = agg25[c]      || 0;
    const tot24Full  = agg24Full[c]  || 0;
    const tot24Match = agg24Match[c] || 0;
    let v25      = tot25;
    let v24Full  = tot24Full;
    let v24Match = tot24Match;
    if(metrica==="COSTO_HA"){
      const s25 = sup25[c];
      const s24 = sup24[c];
      v25      = s25 ? tot25/s25 : NaN;
      v24Full  = s24 ? tot24Full/s24 : NaN;
      v24Match = s24 ? tot24Match/s24 : NaN;
    }
    const vv25      = Number.isFinite(v25) ? v25 : NaN;
    const vv24Full  = Number.isFinite(v24Full) ? v24Full : NaN;
    const vv24Match = Number.isFinite(v24Match) ? v24Match : NaN;
    const diff = (Number.isFinite(vv25)?vv25:0) - (Number.isFinite(vv24Match)?vv24Match:0);
    const pct  = (Number.isFinite(vv24Match) && Math.abs(vv24Match)>0) ? (vv25/vv24Match - 1) : NaN;
    return {CUARTEL:c, v24m:vv24Match, v24t:vv24Full, v25:vv25, diff, pct};
  }).filter(r => !(Number.isNaN(r.v24m) && Number.isNaN(r.v25)));
  const ord = filtros.orden==="Asc" ? "Asc" : "Desc";
  const ordenBy = state.comparativo?.ordenBy || "v25";
  const getOrdenValor = (row)=>{
    const value = ordenBy === "v24t" ? row.v24t
      : ordenBy === "v24m" ? row.v24m
      : ordenBy === "diff" ? row.diff
      : ordenBy === "pct" ? row.pct
      : row.v25;
    return Number.isFinite(value) ? value : 0;
  };
  rowsComp.sort((a,b)=>{
    const va = getOrdenValor(a);
    const vb = getOrdenValor(b);
    return ord==="Asc" ? va-vb : vb-va;
  });
  if(!rowsComp.length){
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--muted)">Sin resultados para los filtros seleccionados en Comparativo.</td></tr>`;
    tfoot.innerHTML = "";
    return;
  }
  let bodyHTML = "";
  for(const r of rowsComp){
    const cu = r.CUARTEL;
    bodyHTML += `<tr class="comp-main">
      <td>${cu}</td>
      <td>${fmt(r.v24t)}</td>
      <td>${fmt(r.v24m)}</td>
      <td>${fmt(r.v25)}</td>
      <td>${fmt(r.diff)}</td>
      <td>${pctFmt(r.pct)}</td>
    </tr>
    <tr class="hidden mini comp-mini-row"><td colspan="6"><div class="mini-wrap"></div></td></tr>`;
  }
  tbody.innerHTML = bodyHTML;
  // Constructor de detalle por Nivel 1 dentro de la propia tabla
  function renderComparativoMini(cu, miniRow){
    const wrap = miniRow.querySelector('.mini-wrap');
    if(!wrap) return;
    const cuNorm = strip(cu||"");
    const rows25Cu      = rows25.filter(r => strip(r.CUARTEL||"")===cuNorm);
    const rows24MatchCu = rows24Match.filter(r => strip(r.CUARTEL||"")===cuNorm);
    if(!rows25Cu.length && !rows24MatchCu.length){
      wrap.innerHTML = "";
      return;
    }
    const agg25N1      = {};
    const agg24MatchN1 = {};
    for(const r of rows25Cu){
      const n = strip(r.NIVEL_1 || "Sin clasificar");
      agg25N1[n] = (agg25N1[n]||0) + (r.VALOR||0);
    }
    for(const r of rows24MatchCu){
      const n = strip(r.NIVEL_1 || "Sin clasificar");
      agg24MatchN1[n] = (agg24MatchN1[n]||0) + (r.VALOR||0);
    }
    const niveles = Array.from(new Set([...Object.keys(agg25N1), ...Object.keys(agg24MatchN1)])).filter(Boolean);
    if(!niveles.length){
      wrap.innerHTML = "";
      return;
    }
    const met = metrica;
    const s25 = sup25[cuNorm];
    const s24 = sup24[cuNorm];
    const rowsDetalle = niveles.map(n1=>{
      const tot25      = agg25N1[n1]      || 0;
      const tot24Match = agg24MatchN1[n1] || 0;
      let v25      = tot25;
      let v24Match = tot24Match;
      if(met==="COSTO_HA"){
        v25      = s25 ? tot25/s25 : NaN;
        v24Match = s24 ? tot24Match/s24 : NaN;
      }
      const vv25      = Number.isFinite(v25)?v25:NaN;
      const vv24Match = Number.isFinite(v24Match)?v24Match:NaN;
      const diff = (Number.isFinite(vv25)?vv25:0) - (Number.isFinite(vv24Match)?vv24Match:0);
      const pct  = (Number.isFinite(vv24Match) && Math.abs(vv24Match)>0) ? (vv25/vv24Match - 1) : NaN;
      return {NIVEL_1:n1, v24:vv24Match, v25:vv25, diff, pct};
    }).filter(r => !(Number.isNaN(r.v24) && Number.isNaN(r.v25)));
    const ord2 = ord;
    const ordenByDetalle = state.comparativo?.ordenBy || "v25";
    const getOrdenDetalle = (row)=>{
      const value = ordenByDetalle === "v24t" ? row.v24
        : ordenByDetalle === "v24m" ? row.v24
        : ordenByDetalle === "diff" ? row.diff
        : ordenByDetalle === "pct" ? row.pct
        : row.v25;
      return Number.isFinite(value) ? value : 0;
    };
    rowsDetalle.sort((a,b)=>{
      const va = getOrdenDetalle(a);
      const vb = getOrdenDetalle(b);
      return ord2==="Asc" ? va-vb : vb-va;
    });
    const sum24 = rowsDetalle.reduce((a,r)=>a+(Number.isFinite(r.v24)?r.v24:0),0);
    const sum25 = rowsDetalle.reduce((a,r)=>a+(Number.isFinite(r.v25)?r.v25:0),0);
    const diffTot = sum25 - sum24;
    const pctTot = (Number.isFinite(sum24) && Math.abs(sum24)>0) ? (sum25/sum24 - 1) : NaN;
    let inner = '<table><thead><tr><th>Categoría</th><th>24-25 (mismos meses)</th><th>25-26</th><th>Diferencia</th><th>% Dif.</th></tr></thead><tbody>';
    for(const r of rowsDetalle){
      inner += `<tr>
        <td>${r.NIVEL_1}</td>
        <td>${fmt(r.v24)}</td>
        <td>${fmt(r.v25)}</td>
        <td>${fmt(r.diff)}</td>
        <td>${pctFmt(r.pct)}</td>
      </tr>`;
    }
    inner += `</tbody><tfoot><tr>
      <td>Total</td>
      <td>${fmt(sum24)}</td>
      <td>${fmt(sum25)}</td>
      <td>${fmt(diffTot)}</td>
      <td>${pctFmt(pctTot)}</td>
    </tr></tfoot></table>`;
    wrap.innerHTML = inner;
  }
  // Click en fila principal para mostrar detalle bajo el cuartel
  Array.from(tbody.querySelectorAll('tr.comp-main')).forEach(tr=>{
    const first = tr.firstElementChild;
    if(!first) return;
    const cu = strip(first.textContent||"");
    tr.style.cursor = 'pointer';
    tr.onclick = ()=>{
      const mini = tr.nextElementSibling;
      if(!mini || !mini.classList.contains('comp-mini-row')) return;
      if(state.filtros.nivel1!=="Todos"){
        mini.classList.add('hidden');
        return;
      }
      if(!mini.classList.contains('hidden')){
        mini.classList.add('hidden');
        return;
      }
      // cerrar otros detalles abiertos
      tbody.querySelectorAll('tr.comp-mini-row').forEach(m=>{
        if(m!==mini) m.classList.add('hidden');
      });
      renderComparativoMini(cu, mini);
      mini.classList.remove('hidden');
    };
  });
  const sum24m = rowsComp.reduce((a,r)=>a+(Number.isFinite(r.v24m)?r.v24m:0),0);
  const sum24t = rowsComp.reduce((a,r)=>a+(Number.isFinite(r.v24t)?r.v24t:0),0);
  const sum25  = rowsComp.reduce((a,r)=>a+(Number.isFinite(r.v25)?r.v25:0),0);
  const diffTot = sum25 - sum24m;
  const pctTot  = (Number.isFinite(sum24m) && Math.abs(sum24m)>0) ? (sum25/sum24m - 1) : NaN;
  tfoot.innerHTML = `<tr>
    <td>Total</td>
    <td>${fmt(sum24t)}</td>
    <td>${fmt(sum24m)}</td>
    <td>${fmt(sum25)}</td>
    <td>${fmt(diffTot)}</td>
    <td>${pctFmt(pctTot)}</td>
  </tr>`;
}
function getSelectedMesesCount(rows){
  if(state.filtros.mes === "Todos" || (Array.isArray(state.filtros.mes) && !state.filtros.mes.length)){
    return new Set(rows.map(r=>r.MES_STR).filter(Boolean)).size;
  }
  if(Array.isArray(state.filtros.mes)) return state.filtros.mes.length;
  return state.filtros.mes ? 1 : 0;
}

function getRentabilidadCardConfig(){
  const raw = (typeof dashboardHigueraData !== 'undefined' && dashboardHigueraData.rentabilidadCards) ? dashboardHigueraData.rentabilidadCards : {};
  const merged = {};
  Object.keys(RENT_CARD_DEFAULTS).forEach((key)=>{
    merged[key] = Object.assign({}, RENT_CARD_DEFAULTS[key], raw && raw[key] ? raw[key] : {});
  });
  return Object.entries(merged).sort((a,b)=>(a[1].order||0)-(b[1].order||0));
}
function parseRentNumber(value){
  let raw = strip(value || '');
  if(!raw) return 0;
  raw = raw.replace(/[$€£¥\s ]/g, '').replace(/[^0-9,.-]/g, '');
  if(!raw || raw === '-' || raw === '.' || raw === ',') return 0;
  const commas = (raw.match(/,/g) || []).length;
  const dots = (raw.match(/\./g) || []).length;
  if(commas && dots){
    if(raw.lastIndexOf(',') > raw.lastIndexOf('.')){
      raw = raw.replace(/\./g, '').replace(',', '.');
    } else {
      raw = raw.replace(/,/g, '');
    }
  } else if(dots > 1 && !commas){
    raw = raw.replace(/\./g, '');
  } else if(commas > 1 && !dots){
    raw = raw.replace(/,/g, '');
  } else if(commas === 1 && !dots){
    raw = raw.replace(',', '.');
  }
  const parsed = parseFloat(raw);
  return Number.isFinite(parsed) ? parsed : 0;
}
function ingestRentabilidadCSV(raw){
  const delim = detectDelimiter(raw);
  const rows = parseCSV(raw, delim).filter(r=>r && r.some(c=>strip(c)));
  if(!rows.length) return [];
  const aliasMap = {
    predio:['PREDIO'],
    sector:['SECTOR'],
    especie:['ESPECIE'],
    variedad:['VARIEDAD'],
    cuartel:['CUARTEL'],
    hectareas:['HECTAREAS','HECTAREA','HAS'],
    kilos:['KILOS REALES','KILOS_REAL','KG REALES','KILOS'],
    totalIngresos:['TOTAL INGRESOS','INGRESO TOTAL','INGRESOS TOTALES'],
    totalCostos:['TOTAL COSTOS ACUMULADOS','TOTAL COSTOS','COSTOS ACUMULADOS'],
    resultado:['RESULTADO','MARGEN','UTILIDAD'],
    ingresoHa:['INGRESO HECTAREA','INGRESO POR HECTAREA'],
    costoHa:['COSTO TOTAL POR HECTAREA','COSTO POR HECTAREA','COSTOS POR HECTAREA'],
    ingresosKilo:['INGRESOS POR KILO','INGRESO POR KILO'],
    costoKilo:['COSTO POR KILO','COSTOS POR KILO']
  };
  let headerIndex = 0;
  let bestScore = -1;
  const targets = ['predio','sector','cuartel','hectareas','kilos','totalIngresos','totalCostos','resultado'];
  for(let i=0;i<Math.min(rows.length,40);i++){
    const normalized = rows[i].map(normalizeHeader);
    const score = targets.reduce((acc,key)=> acc + (aliasMap[key].some(alias => normalized.includes(alias)) ? 1 : 0),0);
    if(score > bestScore){ bestScore = score; headerIndex = i; }
  }
  const header = rows[headerIndex].map(normalizeHeader);
  const findIndex = (aliases)=> header.findIndex(h => aliases.some(alias => h === alias || h.startsWith(alias)));
  const map = {
    predio: findIndex(aliasMap.predio),
    sector: findIndex(aliasMap.sector),
    especie: findIndex(aliasMap.especie),
    variedad: findIndex(aliasMap.variedad),
    cuartel: findIndex(aliasMap.cuartel),
    hectareas: findIndex(aliasMap.hectareas),
    kilos: findIndex(aliasMap.kilos),
    totalIngresos: findIndex(aliasMap.totalIngresos),
    totalCostos: findIndex(aliasMap.totalCostos),
    resultado: findIndex(aliasMap.resultado),
    ingresoHa: findIndex(aliasMap.ingresoHa),
    costoHa: findIndex(aliasMap.costoHa),
    ingresosKilo: findIndex(aliasMap.ingresosKilo),
    costoKilo: findIndex(aliasMap.costoKilo)
  };
  return rows.slice(headerIndex+1).map(row=>({
    PREDIO: map.predio >= 0 ? strip(row[map.predio]) : '',
    SECTOR: map.sector >= 0 ? strip(row[map.sector]) : '',
    ESPECIE: map.especie >= 0 ? strip(row[map.especie]) : '',
    VARIEDAD: map.variedad >= 0 ? strip(row[map.variedad]) : '',
    CUARTEL: map.cuartel >= 0 ? strip(row[map.cuartel]) : '',
    HECTAREAS: map.hectareas >= 0 ? parseRentNumber(row[map.hectareas]) : 0,
    KILOS_REALES: map.kilos >= 0 ? parseRentNumber(row[map.kilos]) : 0,
    TOTAL_INGRESOS: map.totalIngresos >= 0 ? parseRentNumber(row[map.totalIngresos]) : 0,
    TOTAL_COSTOS: map.totalCostos >= 0 ? parseRentNumber(row[map.totalCostos]) : 0,
    RESULTADO: map.resultado >= 0 ? parseRentNumber(row[map.resultado]) : 0,
    INGRESO_HECTAREA: map.ingresoHa >= 0 ? parseRentNumber(row[map.ingresoHa]) : 0,
    COSTO_HECTAREA: map.costoHa >= 0 ? parseRentNumber(row[map.costoHa]) : 0,
    INGRESOS_KILO: map.ingresosKilo >= 0 ? parseRentNumber(row[map.ingresosKilo]) : 0,
    COSTO_KILO: map.costoKilo >= 0 ? parseRentNumber(row[map.costoKilo]) : 0,
  })).filter(r=>r.CUARTEL || r.SECTOR || r.PREDIO);
}
async function ensureRentabilidadData(){
  if(rentabilidadStatus === 'ready' || rentabilidadStatus === 'loading') return;
  const inlineRaw = (typeof dashboardHigueraData !== 'undefined' && dashboardHigueraData.rentabilidadCsvInline) ? String(dashboardHigueraData.rentabilidadCsvInline) : '';
  if(strip(inlineRaw)){
    try{
      const inlineRows = ingestRentabilidadCSV(inlineRaw);
      if(inlineRows.length){
        rentabilidadData = inlineRows;
        rentabilidadDataActual = inlineRows.slice();
        rentabilidadStatus = 'ready';
        rentabilidadErrorReason = '';
        return;
      }
    }catch(e){
      console.warn('Fallback inline de rentabilidad inválido', e);
    }
  }
  const url = (typeof dashboardHigueraData !== 'undefined' && dashboardHigueraData.csvRentabilidadUrl) ? dashboardHigueraData.csvRentabilidadUrl : '';
  if(!url){
    rentabilidadStatus = 'error';
    rentabilidadErrorReason = 'No hay endpoint configurado para la base de rentabilidad.';
    return;
  }
  rentabilidadStatus = 'loading';
  try{
    const resp = await fetch(url, {credentials:'same-origin'});
    if(!resp.ok){
      let detail = '';
      try{
        const json = await resp.clone().json();
        detail = (json && (json.message || json.code)) ? (json.message || json.code) : '';
      }catch(err){
        try{ detail = strip(await resp.text()); }catch(_e){}
      }
      throw new Error(detail || ('HTTP ' + resp.status));
    }
    let raw = await resp.text();
    const t = (raw || '').trim();
    if(t.startsWith('"') && t.endsWith('"')){
      try{ raw = JSON.parse(t); }catch(e){}
    }
    rentabilidadData = ingestRentabilidadCSV(raw);
    rentabilidadDataActual = rentabilidadData.slice();
    rentabilidadStatus = rentabilidadData.length ? 'ready' : 'empty';
    rentabilidadErrorReason = '';
  }catch(e){
    console.warn('No se pudo cargar rentabilidad', e);
    rentabilidadStatus = 'error';
    rentabilidadErrorReason = e && e.message ? e.message : 'Error desconocido al leer rentabilidad.';
    rentabilidadData = [];
  }
}
async function ensureRentabilidadComparativo2425(){
  if(rentabilidadComparativoStatus === 'ready' || rentabilidadComparativoStatus === 'loading') return;
  const url = (typeof dashboardHigueraData !== 'undefined' && dashboardHigueraData.csvRentabilidad2425Url) ? dashboardHigueraData.csvRentabilidad2425Url : '';
  if(!url){ rentabilidadComparativoStatus = 'error'; return; }
  rentabilidadComparativoStatus = 'loading';
  try{
    const resp = await fetch(url, {credentials:'same-origin'});
    if(!resp.ok) throw new Error('HTTP ' + resp.status);
    const raw = await resp.text();
    rentabilidadData2425 = ingestRentabilidadCSV(raw);
    rentabilidadComparativoStatus = 'ready';
  }catch(e){
    rentabilidadData2425 = [];
    rentabilidadComparativoStatus = 'error';
  }
}
function buildRentabilidadAggByCuartel(rows){
  const grouped = new Map();
  (rows||[]).forEach(r=>{
    const key = strip(r.CUARTEL) || 'Sin cuartel';
    if(!grouped.has(key)) grouped.set(key,{cuartel:key,hectareas:0,kilos:0,ingresos:0,costos:0,resultado:0});
    const g = grouped.get(key);
    g.hectareas += Number(r.HECTAREAS)||0;
    g.kilos += Number(r.KILOS_REALES)||0;
    g.ingresos += Number(r.TOTAL_INGRESOS)||0;
    g.costos += Number(r.TOTAL_COSTOS)||0;
    g.resultado += Number(r.RESULTADO)||0;
  });
  return grouped;
}
function syncRentMetricaChips(activeValue){
  const chips = document.querySelectorAll('.rent-metrica-chip[data-rent-metrica]');
  chips.forEach(chip=>{
    const isActive = chip.dataset.rentMetrica === activeValue;
    chip.classList.toggle('is-active', isActive);
    chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
  });
}
function getRentabilidadMetricFromAgg(agg, metric){
  if(metric === 'ingresos') return agg.ingresos;
  if(metric === 'costos') return agg.costos;
  if(metric === 'kilos') return agg.kilos;
  if(metric === 'ingreso_kg') return agg.kilos>0 ? agg.ingresos/agg.kilos : 0;
  if(metric === 'costo_kg') return agg.kilos>0 ? agg.costos/agg.kilos : 0;
  if(metric === 'ingreso_ha') return agg.hectareas>0 ? agg.ingresos/agg.hectareas : 0;
  if(metric === 'costo_ha') return agg.hectareas>0 ? agg.costos/agg.hectareas : 0;
  return agg.resultado;
}
function isRentCostMetric(metricKey){
  return metricKey === 'costos' || metricKey === 'costo_kg' || metricKey === 'costo_ha';
}
function getMetricTone(metricKey, value, diffValue){
  const hasDiff = Number.isFinite(diffValue);
  const metricIsCost = isRentCostMetric(metricKey);
  if(hasDiff){
    if(diffValue === 0) return 'neutral';
    if(metricIsCost) return diffValue < 0 ? 'positive' : 'negative';
    return diffValue > 0 ? 'positive' : 'negative';
  }
  if(metricKey === 'resultado'){
    if(value === 0 || !Number.isFinite(value)) return 'neutral';
    return value > 0 ? 'positive' : 'negative';
  }
  return 'neutral';
}
function renderRentabilidadComparativo(rows24, rows25){
  const tableWrap = document.getElementById('rentabilidad-resumen');
  const metricSel = document.getElementById('rent-metrica');
  if(!tableWrap || !metricSel) return;
  const g24 = buildRentabilidadAggByCuartel(rows24), g25 = buildRentabilidadAggByCuartel(rows25);
  const all = Array.from(new Set([...g24.keys(), ...g25.keys()])).sort((a,b)=>a.localeCompare(b,'es'));
  const totals24 = {res:0,costoKg:0,costos:0,kilos:0}, totals25={res:0,costoKg:0,costos:0,kilos:0};
  (rows24||[]).forEach(r=>{totals24.res+=(r.RESULTADO||0);totals24.costos+=(r.TOTAL_COSTOS||0);totals24.kilos+=(r.KILOS_REALES||0);});
  (rows25||[]).forEach(r=>{totals25.res+=(r.RESULTADO||0);totals25.costos+=(r.TOTAL_COSTOS||0);totals25.kilos+=(r.KILOS_REALES||0);});
  totals24.costoKg = totals24.kilos>0?totals24.costos/totals24.kilos:0; totals25.costoKg = totals25.kilos>0?totals25.costos/totals25.kilos:0;
  const metricKey = metricSel.value || 'resultado';
  const metricLabelMap = {
    resultado: 'Resultado',
    ingresos: 'Ingresos',
    costos: 'Costos',
    kilos: 'Kilos',
    ingreso_kg: 'Ingreso/kg',
    costo_kg: 'Costo/kg',
    ingreso_ha: 'Ingreso/ha',
    costo_ha: 'Costo/ha'
  };
  const metricLabel = metricLabelMap[metricKey] || 'Resultado';
  const totalsAgg24 = {hectareas:0,kilos:totals24.kilos,ingresos:0,costos:totals24.costos,resultado:totals24.res};
  const totalsAgg25 = {hectareas:0,kilos:totals25.kilos,ingresos:0,costos:totals25.costos,resultado:totals25.res};
  (rows24||[]).forEach(r=>{ totalsAgg24.hectareas += (r.HECTAREAS || 0); totalsAgg24.ingresos += (r.TOTAL_INGRESOS || 0); });
  (rows25||[]).forEach(r=>{ totalsAgg25.hectareas += (r.HECTAREAS || 0); totalsAgg25.ingresos += (r.TOTAL_INGRESOS || 0); });
  const totalMetric24 = getRentabilidadMetricFromAgg(totalsAgg24, metricKey);
  const totalMetric25 = getRentabilidadMetricFromAgg(totalsAgg25, metricKey);
  const diffMetric = totalMetric25 - totalMetric24;
  const diffMetricPct = totalMetric24 !== 0 ? (diffMetric / totalMetric24) : 0;
  const costoKgDiff = totals25.costoKg - totals24.costoKg;
  const tMetric24 = getMetricTone(metricKey, totalMetric24);
  const tMetric25 = getMetricTone(metricKey, totalMetric25, diffMetric);
  const tDiff = getMetricTone(metricKey, diffMetric, diffMetric);
  const tPct = getMetricTone(metricKey, diffMetricPct, diffMetricPct);
  const tCosto24 = getMetricTone('costo_kg', totals24.costoKg);
  const tCosto25 = getMetricTone('costo_kg', totals25.costoKg, costoKgDiff);
  document.getElementById('rentabilidad-cards').innerHTML = `
  <article class="rent-card metric-${tMetric24}"><div class="rent-card-label">${metricLabel} 24-25</div><div class="rent-card-value metric-${tMetric24}">${fmt(totalMetric24)}</div></article>
  <article class="rent-card metric-${tMetric25}"><div class="rent-card-label">${metricLabel} 25-26</div><div class="rent-card-value metric-${tMetric25}">${fmt(totalMetric25)}</div></article>
  <article class="rent-card metric-${tDiff}"><div class="rent-card-label">Diferencia</div><div class="rent-card-value metric-${tDiff}">${fmt(diffMetric)}</div></article>
  <article class="rent-card metric-${tPct}"><div class="rent-card-label">% Dif.</div><div class="rent-card-value metric-${tPct}">${pctFmt(diffMetricPct)}</div></article>
  <article class="rent-card metric-${tCosto24}"><div class="rent-card-label">Costo/kg 24-25</div><div class="rent-card-value metric-${tCosto24}">${fmt(totals24.costoKg)}</div></article>
  <article class="rent-card metric-${tCosto25}"><div class="rent-card-label">Costo/kg 25-26</div><div class="rent-card-value metric-${tCosto25}">${fmt(totals25.costoKg)}</div></article>`;
  tableWrap.innerHTML = `<table class="dense-table resumen-table rent-table rent-table--compare"><thead><tr><th>Cuartel</th><th>24-25</th><th>25-26</th><th>Diferencia</th><th>% Dif.</th></tr></thead><tbody>${
    all.map(k=>{const base={hectareas:0,kilos:0,ingresos:0,costos:0,resultado:0};const v24=getRentabilidadMetricFromAgg(g24.get(k)||base, metricSel.value);const v25=getRentabilidadMetricFromAgg(g25.get(k)||base, metricSel.value);const d=v25-v24;const p=v24!==0?d/v24:0;const dCls=d<0?'is-neg-cell':'is-pos-cell';const pCls=p<0?'is-neg-cell':'is-pos-cell';return `<tr><td>${k}</td><td>${fmt(v24)}</td><td>${fmt(v25)}</td><td class="${dCls}">${fmt(d)}</td><td class="${pCls}">${pctFmt(p)}</td></tr>`;}).join('')
  }</tbody></table>`;
}

function applyRentabilidadDetailFilters(sourceRows){
  let rows = Array.isArray(sourceRows) ? sourceRows.slice() : [];
  if(state.filtros.predio!=="Todos"){
    rows = rows.filter(r => (state.filtros.predio==="Solo Productivo" ? predioClas(r)==="Productivo" : predioClas(r)==="Indirectos"));
  }
  const selectedSectores = state.filtros.sector==="Todos"
    ? []
    : (Array.isArray(state.filtros.sector) ? state.filtros.sector : [state.filtros.sector]).filter(Boolean).filter(s => s !== '__COSTOS_INDIRECTOS__');
  if(selectedSectores.length){
    rows = rows.filter(r => selectedSectores.includes(strip(r.SECTOR||"") || "Sin dato"));
  }
  if(state.filtros.cuartel!=="Todos"){
    const cuarteles = Array.isArray(state.filtros.cuartel) ? state.filtros.cuartel : [state.filtros.cuartel];
    if(!cuarteles.length) return [];
    rows = rows.filter(r => cuarteles.includes(strip(r.CUARTEL||"") || "—"));
  }
  return rows;
}
function applyRentabilidadFilters(){
  return applyRentabilidadFiltersToRows(rentabilidadData);
}
function applyRentabilidadFiltersToRows(sourceRows){
  let rows = Array.isArray(sourceRows) ? sourceRows.slice() : [];
  const isIndirectoRent = (row)=>{
    const values = [row.PREDIO, row.CUARTEL, row.SECTOR].map(v => strip(v||'').toUpperCase());
    return values.some(v => v === 'COSTOS INDIRECTOS');
  };
  if(state.filtros.predio === 'Solo Productivo'){
    rows = rows.filter(r => !isIndirectoRent(r));
  }else if(state.filtros.predio === 'Solo Costos Indirectos'){
    rows = rows.filter(r => isIndirectoRent(r));
  }
  const selectedSectores = state.filtros.sector === 'Todos'
    ? []
    : (Array.isArray(state.filtros.sector) ? state.filtros.sector : [state.filtros.sector]).filter(Boolean).filter(s => s !== '__COSTOS_INDIRECTOS__');
  if(selectedSectores.length){
    rows = rows.filter(r => selectedSectores.includes(strip(r.SECTOR)||'Sin dato'));
  }
  if(state.filtros.cuartel !== 'Todos'){
    const selectedCuarteles = Array.isArray(state.filtros.cuartel) ? state.filtros.cuartel : [state.filtros.cuartel];
    rows = rows.filter(r => selectedCuarteles.includes(strip(r.CUARTEL||'') || '—'));
  }
  return rows;
}

function getRentabilidadCompoundKey(row){
  return [strip(row.PREDIO||''), strip(row.SECTOR||''), strip(row.CUARTEL||'')].join('|');
}
function getRentabilidadCostMapFromDetailRows(detailRows){
  const map = new Map();
  (Array.isArray(detailRows) ? detailRows : []).forEach(r=>{
    const key = getRentabilidadCompoundKey(r);
    if(key === '||') return;
    const value = Number(r.VALOR)||0;
    if(!map.has(key)) map.set(key, {bruto:0, inversiones:0});
    const item = map.get(key);
    item.bruto += value;
    if(isInversionRow(r)) item.inversiones += value;
  });
  return map;
}
function enrichRentabilidadRowsWithCostBreakdown(rows, detailRows){
  const costMap = getRentabilidadCostMapFromDetailRows(detailRows);
  return (Array.isArray(rows) ? rows : []).map(r=>{
    const key = getRentabilidadCompoundKey(r);
    const costs = costMap.get(key);
    const bruto = costs ? Number(costs.bruto)||0 : Number(r.TOTAL_COSTOS)||0;
    const inversiones = costs ? Number(costs.inversiones)||0 : 0;
    const sinInv = bruto - inversiones;
    const totalCostos = hideInv2425 ? sinInv : bruto;
    const totalIngresos = Number(r.TOTAL_INGRESOS)||0;
    return Object.assign({}, r, {
      total_costos_bruto: bruto,
      total_costos_inversiones_varias: inversiones,
      total_costos_sin_inversiones_varias: sinInv,
      TOTAL_COSTOS: totalCostos,
      RESULTADO: totalIngresos - totalCostos
    });
  });
}
function getRentMetricValue(rows, key){
  if(!rows.length) return 0;
  if(key === 'total_ingresos') return rows.reduce((a,r)=>a+(r.TOTAL_INGRESOS||0),0);
  if(key === 'total_costos') return rows.reduce((a,r)=>a+(r.TOTAL_COSTOS||0),0);
  if(key === 'resultado') return rows.reduce((a,r)=>a+(r.RESULTADO||0),0);
  if(key === 'kilos_reales') return rows.reduce((a,r)=>a+(r.KILOS_REALES||0),0);
  if(key === 'ingreso_hectarea'){
    const has = rows.reduce((a,r)=>a+(r.HECTAREAS||0),0);
    const total = rows.reduce((a,r)=>a+(r.TOTAL_INGRESOS||0),0);
    return has > 0 ? total/has : 0;
  }
  if(key === 'costo_hectarea'){
    const has = rows.reduce((a,r)=>a+(r.HECTAREAS||0),0);
    const total = rows.reduce((a,r)=>a+(r.TOTAL_COSTOS||0),0);
    return has > 0 ? total/has : 0;
  }
  if(key === 'ingresos_kilo'){
    const kilos = rows.reduce((a,r)=>a+(r.KILOS_REALES||0),0);
    const total = rows.reduce((a,r)=>a+(r.TOTAL_INGRESOS||0),0);
    return kilos > 0 ? total/kilos : 0;
  }
  if(key === 'costo_kilo'){
    const kilos = rows.reduce((a,r)=>a+(r.KILOS_REALES||0),0);
    const total = rows.reduce((a,r)=>a+(r.TOTAL_COSTOS||0),0);
    return kilos > 0 ? total/kilos : 0;
  }
  return 0;
}
function getRentEmptyStateHTML(message, cls='is-empty'){
  return `<div class="rent-empty-state ${cls}"><strong>Rentabilidad no disponible</strong><p>${message}</p></div>`;
}
function renderRentabilidadResumen(){
  const block = document.getElementById('rentabilidad-resumen-block');
  const cardsWrap = document.getElementById('rentabilidad-cards');
  const tableWrap = document.getElementById('rentabilidad-resumen');
  if(!block || !cardsWrap || !tableWrap) return;
  block.classList.remove('hidden');

  if(rentabilidadStatus === 'loading'){
    cardsWrap.innerHTML = '';
    tableWrap.innerHTML = getRentEmptyStateHTML('Cargando base de rentabilidad…', 'is-loading');
    return;
  }
  if(rentabilidadStatus === 'error'){
    cardsWrap.innerHTML = '';
    const reason = rentabilidadErrorReason ? ` Detalle: ${rentabilidadErrorReason}` : '';
    tableWrap.innerHTML = getRentEmptyStateHTML('No se pudo leer la base activa o la base fuente de rentabilidad. Revisa Ajustes > Rentabilidad o Base 24-25.' + reason, 'is-error');
    return;
  }
  if(!rentabilidadData.length){
    cardsWrap.innerHTML = '';
    tableWrap.innerHTML = getRentEmptyStateHTML('Todavía no hay datos renderizables para rentabilidad. El bloque queda disponible como apoyo del resumen.', 'is-empty');
    return;
  }

  const rowsBase = applyRentabilidadFilters();
  const detailRows25 = applyRentabilidadDetailFilters(state.data);
  const rows = enrichRentabilidadRowsWithCostBreakdown(rowsBase, detailRows25);
  const activeRentTab = document.querySelector('.rent-tabs .tab.active')?.dataset?.rentTab || 'resumen';
  if(activeRentTab === 'comparativo'){
    if(rentabilidadComparativoStatus === 'error'){
      cardsWrap.innerHTML = '';
      tableWrap.innerHTML = getRentEmptyStateHTML('No se pudo cargar la base comparativa 24-25. Revisa el importador "Base comparativa rentabilidad 24-25".', 'is-error');
      return;
    }
    const rows24Base = applyRentabilidadFiltersToRows(rentabilidadData2425);
    if(comp2425Status === 'idle' || comp2425Status === 'loading'){
      cardsWrap.innerHTML = '';
      tableWrap.innerHTML = getRentEmptyStateHTML('Cargando base detallada 24-25 para comparativo de rentabilidad…', 'is-loading');
      ensureComparativo2425Data().then(()=>renderRentabilidadResumen());
      return;
    }
    const detailRows24 = comp2425 && Array.isArray(comp2425.rows) ? applyRentabilidadDetailFilters(comp2425.rows) : [];
    const rows24 = enrichRentabilidadRowsWithCostBreakdown(rows24Base, detailRows24);
    renderRentabilidadComparativo(rows24, rows);
    return;
  }
  const config = getRentabilidadCardConfig();
  cardsWrap.innerHTML = config.filter(([,cfg])=>String(cfg.enabled) === '1' || cfg.enabled === 1).map(([key,cfg])=>{
    const value = getRentMetricValue(rows, key);
    const cls = key === 'resultado' ? (value >= 0 ? 'is-positive' : 'is-negative') : '';
    return `<article class="rent-card ${cls}"><div class="rent-card-label">${cfg.label}</div><div class="rent-card-value">${fmt(value)}</div></article>`;
  }).join('');
  const grouped = new Map();
  rows.forEach(r=>{
    const key = strip(r.CUARTEL) || 'Sin cuartel';
    if(!grouped.has(key)) grouped.set(key,{CUARTEL:key,HECTAREAS:0,KILOS_REALES:0,TOTAL_INGRESOS:0,TOTAL_COSTOS:0,RESULTADO:0});
    const g = grouped.get(key);
    g.HECTAREAS += r.HECTAREAS||0; g.KILOS_REALES += r.KILOS_REALES||0; g.TOTAL_INGRESOS += r.TOTAL_INGRESOS||0; g.TOTAL_COSTOS += r.TOTAL_COSTOS||0; g.RESULTADO += r.RESULTADO||0;
  });
  const sortKeyMap = {cuartel:'CUARTEL', hectareas:'HECTAREAS', kilos:'KILOS_REALES', ingresos:'TOTAL_INGRESOS', costos:'TOTAL_COSTOS', resultado:'RESULTADO'};
  const sortKey = sortKeyMap[rentabilidadState.sortBy] || 'RESULTADO';
  const dir = rentabilidadState.sortDir === 'asc' ? 1 : -1;
  const data = Array.from(grouped.values()).sort((a,b)=>{
    const av = a[sortKey]; const bv = b[sortKey];
    if(sortKey === 'CUARTEL') return String(av).localeCompare(String(bv),'es') * dir;
    return ((Number(av)||0) - (Number(bv)||0)) * dir;
  });
  const headers = [
    ['cuartel','Cuartel'],
    ['hectareas','Has'],
    ['kilos','Kilos'],
    ['ingresos','Ingresos'],
    ['costos','Costos'],
    ['resultado','Resultado']
  ];
  const table = data.length ? `<table class="dense-table resumen-table rent-table"><thead><tr>${headers.map(([key,label])=>`<th><button type="button" class="rent-sort-btn ${rentabilidadState.sortBy===key?'is-active':''}" data-rent-sort="${key}">${label}${rentabilidadState.sortBy===key ? `<span>${rentabilidadState.sortDir==='asc'?'↑':'↓'}</span>` : ''}</button></th>`).join('')}</tr></thead><tbody>${data.map(r=>`<tr><td>${r.CUARTEL}</td><td>${fmt(r.HECTAREAS)}</td><td>${fmt(r.KILOS_REALES)}</td><td>${fmt(r.TOTAL_INGRESOS)}</td><td>${fmt(r.TOTAL_COSTOS)}</td><td class="${r.RESULTADO<0?'is-neg-cell':'is-pos-cell'}">${fmt(r.RESULTADO)}</td></tr>`).join('')}</tbody></table>` : getRentEmptyStateHTML('No hay filas para los filtros seleccionados.');
  const rentNote = hideInv2425 ? '<div class="rent-filter-note">Costos excluyen INVERSIONES VARIAS</div>' : '';
  tableWrap.innerHTML = rentNote + table;
  tableWrap.querySelectorAll('[data-rent-sort]').forEach(btn=>btn.addEventListener('click', ()=>{
    const key = btn.dataset.rentSort;
    if(rentabilidadState.sortBy === key){
      rentabilidadState.sortDir = rentabilidadState.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      rentabilidadState.sortBy = key;
      rentabilidadState.sortDir = key === 'cuartel' ? 'asc' : 'desc';
    }
    renderRentabilidadResumen();
  }));
}
function renderResumenSignals(){
  const wrap = document.getElementById('resumen-signal-cards');
  const note = document.getElementById('nota-resumen');
  if(!wrap) return;
  const rows = applyFilters();
  const total = rows.reduce((a,r)=>a+(r.VALOR||0),0);
  const cuarteles = Array.from(new Set(rows.map(r=>r.CUARTEL).filter(Boolean)));
  const sumSup = cuarteles.reduce((a,c)=>a + (state.supMap[c]||0), 0);
  const costoHa = sumSup > 0 ? total / sumSup : NaN;
  const meses = (state.filtros.mes === 'Todos' || (Array.isArray(state.filtros.mes) && !state.filtros.mes.length))
    ? Array.from(new Set(rows.map(r=>r.MES_STR).filter(Boolean))).sort((a,b)=>{
        const [ma,ya]=String(a).split('-');
        const [mb,yb]=String(b).split('-');
        if((parseInt(ya,10)||0)!==(parseInt(yb,10)||0)) return (parseInt(ya,10)||0)-(parseInt(yb,10)||0);
        return MES_ABR.indexOf(ma)-MES_ABR.indexOf(mb);
      })
    : (Array.isArray(state.filtros.mes) ? state.filtros.mes.slice() : [state.filtros.mes]);
  const ultimoMes = meses.length ? meses[meses.length - 1] : '—';
  wrap.innerHTML = [
    {label:'Foco actual', value: ultimoMes, help:`${fmt(cuarteles.length)} cuarteles visibles con filtros activos.`},
    {label: state.filtros.metrica === 'COSTO_HA' ? 'Costo agregado / ha' : 'Costo agregado', value: fmt(state.filtros.metrica === 'COSTO_HA' ? costoHa : total), help: state.filtros.metrica === 'COSTO_HA' ? 'Promedio ponderado según superficie visible.' : 'Suma acumulada del universo filtrado.'},
    {label:'Lectura aplicada', value: state.filtros.orden === 'Asc' ? 'Ascendente' : 'Descendente', help:`Métrica: ${state.filtros.metrica === 'COSTO_HA' ? 'Costo por hectárea' : 'Gasto total'}.`}
  ].map(card => `<article class="resumen-signal-card"><div class="resumen-signal-label">${card.label}</div><div class="resumen-signal-value">${card.value}</div><div class="resumen-signal-help">${card.help}</div></article>`).join('');
  if(note){
    note.textContent = `Resumen por cuartel para ${fmt(cuarteles.length)} cuarteles visibles${ultimoMes !== '—' ? ` · último mes visible: ${ultimoMes}` : ''}.`;
    note.classList.remove('hidden');
  }
}


function syncGlobalInvToggleUI(btn){
  if(!btn) return;
  const title = btn.querySelector('.global-inv-toggle__title');
  const meta = btn.querySelector('.global-inv-toggle__meta');
  const on = !!hideInv2425;
  if(title) title.textContent = on ? 'Inversiones ocultas' : 'Inversiones incluidas';
  if(meta) meta.textContent = on ? 'Se excluyen INVERSIONES VARIAS' : 'Costos completos';
  btn.setAttribute('aria-pressed', on ? 'true' : 'false');
  btn.classList.toggle('is-active', on);
}
function renderKpis(){
  const wrap = document.getElementById('kpi-row');
  if(!wrap) return;
  const rows = applyFilters();
  const total = rows.reduce((a,r)=>a+(r.VALOR||0),0);
  const cuSet = new Set(rows.map(r=>r.CUARTEL).filter(Boolean));
  const sup = Array.from(cuSet).reduce((a,c)=>a+(state.supMap[c]||0),0);
  const costoHa = sup>0 ? total/sup : NaN;
  const mesesCount = getSelectedMesesCount(rows);
  wrap.innerHTML = `
    <article class="kpi-card kpi-card--premium"><div class="kpi-label">Gasto total</div><div class="kpi-value">${fmt(total)}</div><div class="kpi-meta">Universo filtrado acumulado</div></article>
    <article class="kpi-card kpi-card--premium"><div class="kpi-label">Costo por hectárea</div><div class="kpi-value">${fmt(costoHa)}</div><div class="kpi-meta">Promedio sobre ${fmt(sup)} ha visibles</div></article>
    <article class="kpi-card kpi-card--premium"><div class="kpi-label">Cuarteles visibles</div><div class="kpi-value">${fmt(cuSet.size)}</div><div class="kpi-meta">Base activa para Resumen y Detalle</div></article>
    <article class="kpi-card kpi-card--premium"><div class="kpi-label">Meses seleccionados</div><div class="kpi-value">${fmt(mesesCount)}</div><div class="kpi-meta">Rango aplicado al tablero</div></article>
  `;
}
function renderActiveFilterChips(){
  const wrap = document.getElementById('active-filters-chips');
  if(!wrap) return;
  const chips = [];
  if(state.filtros.predio !== 'Todos') chips.push({ key:'predio', value: state.filtros.predio, text:`Tipo de registro: ${state.filtros.predio}` });
  if(state.filtros.sector !== 'Todos'){
    const sectorTxt = Array.isArray(state.filtros.sector) ? (state.filtros.sector.length ? state.filtros.sector.join(', ') : 'Ninguno') : state.filtros.sector;
    chips.push({ key:'sector', value: sectorTxt, text:`Cultivo: ${sectorTxt}` });
  }
  if(state.filtros.cuartel !== 'Todos'){
    const cuTxt = Array.isArray(state.filtros.cuartel) ? (state.filtros.cuartel.length ? state.filtros.cuartel.join(', ') : 'Ninguno') : state.filtros.cuartel;
    chips.push({ key:'cuartel', value: cuTxt, text:`Cuartel: ${cuTxt}` });
  }
  if(state.filtros.nivel1 !== 'Todos') chips.push({ key:'nivel1', value: state.filtros.nivel1, text:`Categoría: ${state.filtros.nivel1}` });
  if(state.filtros.faena !== 'Todas') chips.push({ key:'faena', value: state.filtros.faena, text:`Faena: ${state.filtros.faena}` });
  if(state.filtros.mes !== 'Todos'){
    const mesValue = Array.isArray(state.filtros.mes) ? (state.filtros.mes.length ? state.filtros.mes.join(', ') : 'Ninguno') : state.filtros.mes;
    chips.push({ key:'mes', value: mesValue, text:`Meses: ${mesValue}` });
  }
  if(hideInv2425) chips.push({ key:'hideInv2425', value:'ocultas', text:'Sin INVERSIONES VARIAS' });
  wrap.innerHTML = chips.length
    ? chips.map(c=>`<button class="filter-chip" type="button" data-chip-key="${c.key}" aria-label="Quitar filtro ${c.text}"><span>${c.text}</span><span class="filter-chip-remove" aria-hidden="true">×</span></button>`).join('')
    : `<span class="filter-chip is-muted">Sin filtros activos</span>`;
}
function renderCollapsedSidebarSummary(){
  const node = document.getElementById('sidebarCollapsedFilterSummary');
  if(!node) return;
  const parts = [];
  if(state.filtros.predio !== 'Todos') parts.push(`Tipo: ${state.filtros.predio}`);
  if(state.filtros.sector !== 'Todos'){
    const sectorTxt = Array.isArray(state.filtros.sector) ? (state.filtros.sector.length ? state.filtros.sector.join(', ') : 'Ninguno') : state.filtros.sector;
    parts.push(`Cultivo: ${sectorTxt}`);
  }
  if(state.filtros.cuartel !== 'Todos'){
    const cuTxt = Array.isArray(state.filtros.cuartel) ? (state.filtros.cuartel.length ? state.filtros.cuartel.join(', ') : 'Ninguno') : state.filtros.cuartel;
    parts.push(`Cuartel: ${cuTxt}`);
  }
  if(state.filtros.nivel1 !== 'Todos') parts.push(`Categoría: ${state.filtros.nivel1}`);
  if(state.filtros.faena !== 'Todas') parts.push(`Faena: ${state.filtros.faena}`);
  if(state.filtros.mes !== 'Todos'){
    const mesTxt = Array.isArray(state.filtros.mes) ? (state.filtros.mes.length ? state.filtros.mes.join(', ') : 'Ninguno') : state.filtros.mes;
    parts.push(`Meses: ${mesTxt}`);
  }
  if(hideInv2425) parts.push('Sin INVERSIONES VARIAS');
  node.textContent = parts.length ? `Filtros activos: ${parts.join(' · ')}` : 'Sin filtros activos';
}
function clearFilterChip(key){
  if(key === 'hideInv2425'){
    if(!hideInv2425) return;
    hideInv2425 = false;
    const btnInv = document.getElementById('btnToggleInv2425');
    if(btnInv){
      syncGlobalInvToggleUI(btnInv);
    }
    try{ localStorage.setItem(HIDE_INV_STORAGE_KEY, '0'); }catch(e){}
    refreshAll();
    return;
  }
  const meta = FILTER_CHIP_META[key];
  if(!meta || !meta.stateKey) return;
  state.filtros[meta.stateKey] = meta.clearValue;
  refreshAll();
}

function syncFilterInputsWithState(){
  const predio = document.querySelector('#f_predio');
  const cultivo = document.querySelector('#f_cultivo');
  const cuartel = document.querySelector('#f_cuartel');
  const categoria = document.querySelector('#f_n1');
  const faena = document.querySelector('#f_faena');
  const metrica = document.querySelector('#f_metrica');
  const orden = document.querySelector('#f_orden');
  const dN1 = document.querySelector('#d_n1');
  const dMetrica = document.querySelector('#d_metrica');
  const dOrden = document.querySelector('#d_orden');
  if(predio) predio.value = state.filtros.predio;
  if(cultivo) cultivo.value = Array.isArray(state.filtros.sector) ? 'Todos' : state.filtros.sector;
  if(cuartel) cuartel.dataset.value = Array.isArray(state.filtros.cuartel) ? state.filtros.cuartel.join('|') : String(state.filtros.cuartel||'Todos');
  if(categoria) categoria.value = state.filtros.nivel1;
  if(faena) faena.value = state.filtros.faena;
  if(metrica) metrica.value = state.filtros.metrica;
  if(orden) orden.value = state.filtros.orden;
  if(dN1) dN1.value = state.detalle.nivel1;
  if(dMetrica) dMetrica.value = state.detalle.metrica;
  if(dOrden) dOrden.value = state.detalle.orden;
  const compOrden = document.getElementById('comparativo-orden-by');
  if(compOrden) compOrden.value = state.comparativo?.ordenBy || 'v25';
}
function clearFilters(resetView){
  state.filtros.predio = 'Todos';
  state.filtros.sector = 'Todos';
  state.filtros.cuartel = 'Todos';
  state.filtros.nivel1 = 'Todos';
  state.filtros.faena = 'Todas';
  state.filtros.metrica = 'VALOR';
  state.filtros.mes = 'Todos';
  state.filtros.orden = 'Desc';
  state.detalle.nivel1 = 'Todos';
  state.detalle.metrica = 'VALOR';
  state.detalle.orden = 'Desc';
  state.comparativo.ordenBy = 'v25';
  rentabilidadState.sortBy = 'resultado';
  rentabilidadState.sortDir = 'desc';
  syncFilterInputsWithState();
  refreshAll();
  if(resetView) showTab('resumen');
}
function refreshAll(){
  updateComparativoStatus();
  populateCombos();
  buildResumen();
  buildCharts();
  buildDetalle();
  renderKpis();
  renderResumenSignals();
  renderActiveFilterChips();
  renderCollapsedSidebarSummary();
  renderRentabilidadResumen();
  const activeTab = document.querySelector('.dashboard-main-tabs .tab.active[data-tab]');
  if(activeTab && activeTab.dataset.tab==='comparativo') buildComparativo();
}
async function ensureComparativo2425Data(){
  if(comp2425Status==='ready' && comp2425.rows.length) return true;
  if(comp2425Status==='loading') return false;
  comp2425Status = 'loading';
  comp2425ErrorReason = '';
  const candidates = [];
  if(EMBED_CSV_2425 && EMBED_CSV_2425.trim()) candidates.push(EMBED_CSV_2425);
  const csv2425Url = (typeof dashboardHigueraData !== 'undefined' && dashboardHigueraData.csv2425Url)
    ? dashboardHigueraData.csv2425Url
    : 'data/temporada-2024-25.csv';
  const last2425Updated = (typeof dashboardHigueraData !== 'undefined' && dashboardHigueraData.last2425Updated)
    ? dashboardHigueraData.last2425Updated
    : '';
  const csv2425UrlWithTs = buildUrlWithTs(csv2425Url, last2425Updated);
  comparativoMeta.last2425Updated = last2425Updated || comparativoMeta.last2425Updated;
  comparativoMeta.url2425 = csv2425UrlWithTs;
  updateComparativoStatus();
  if(!candidates.length){
    try{
      const resp = await fetch(csv2425UrlWithTs);
      if(resp.ok){
        let raw = await resp.text();
        // Fallback: algunos servers/implementaciones REST devuelven el CSV envuelto como string JSON.
        // Intentamos des-envolverlo si viene entre comillas.
        const t = (raw || '').trim();
        if(t.startsWith('"') && t.endsWith('"')){
          try{ raw = JSON.parse(t); }catch(e){ /* ignore */ }
        }
        if(raw && String(raw).trim()) candidates.push(raw);
      } else {
        // Intentar capturar detalle de error para debug.
        try{
          const errTxt = await resp.text();
          comp2425ErrorReason = `CSV 24-25 no disponible (HTTP ${resp.status}).`;
          if(errTxt && errTxt.trim() && debugEnabled){
            console.warn('Respuesta error CSV 24-25:', errTxt.slice(0, 500));
          }
        }catch(e){
          comp2425ErrorReason = `CSV 24-25 no disponible (HTTP ${resp.status}).`;
        }
      }
    }catch(e){
      console.warn('No se pudo cargar CSV 24-25 para comparativo', e);
      comp2425ErrorReason = 'No se pudo descargar el CSV 24-25.';
    }
  }
  if(!candidates.length && !comp2425ErrorReason){
    comp2425ErrorReason = 'CSV 24-25 no disponible.';
  }
  for(const raw of candidates){
    try{
      const parsed = ingestCSV2425(raw);
      if(parsed.rows && parsed.rows.length){
        EMBED_CSV_2425 = raw;
        comp2425 = parsed;
        comparativoMeta.rows2425Read = parsed.totalRows || 0;
        comparativoMeta.rows2425Valid = parsed.rows.length || 0;
        if(debugEnabled){
          console.log('CSV 24-25 filas válidas:', comparativoMeta.rows2425Valid, 'filas leídas:', comparativoMeta.rows2425Read);
        }
        updateComparativoStatus();
        comp2425Status = 'ready';
        return true;
      }
      if(parsed.totalRows > 0 && (!parsed.rows || !parsed.rows.length)){
        comp2425ErrorReason = 'CSV 24-25 cargado, pero no contiene filas con temporada 2024-2025 reconocible.';
      }
    }catch(e){
      console.warn('Error parseando CSV 24-25', e);
      comp2425ErrorReason = 'CSV 24-25 inválido o con formato no reconocido.';
    }
  }
  comp2425 = {rows:[], supMap:{}};
  if(!comp2425ErrorReason) comp2425ErrorReason = 'CSV 24-25 vacío o sin filas válidas.';
  comp2425Status = 'error';
  return false;
}
function initDashboardFromRawCSV(raw){
  const ing = ingestCSV(raw); state.data = ing.rows; state.supMap = ing.supMap;
  comparativoMeta.rows2526Read = ing.totalRows || 0;
  comparativoMeta.last2526Updated = new Date().toISOString().slice(0,19).replace('T',' ');
  comparativoMeta.rows2425Read = 0;
  comparativoMeta.rows2425Valid = 0;
  updateComparativoStatus();
  comp2425Status = 'idle';
  comp2425ErrorReason = '';
  document.querySelector("#f_predio").innerHTML = `<option>Todos</option><option>Solo Productivo</option><option>Solo Costos Indirectos</option>`;
  document.querySelector("#f_metrica").innerHTML = `<option value="VALOR">Gasto total</option><option value="COSTO_HA">Costo por hectárea</option>`;
  document.querySelector("#f_orden").innerHTML = `<option value="Desc">Descendente</option><option value="Asc">Ascendente</option>`;
  document.querySelector("#d_metrica").innerHTML = `<option value="VALOR">Gasto total</option><option value="COSTO_HA">Costo por hectárea</option>`;
  document.querySelector("#d_orden").innerHTML = `<option value="Desc">Descendente</option><option value="Asc">Ascendente</option>`;
  setupFaenaSearch();
  refreshAll();
  document.querySelector("#f_predio").onchange = e=>{
    state.filtros.predio = e.target.value;
    if(state.filtros.predio === "Solo Costos Indirectos"){
      state.filtros.sector = "Todos";
      state.filtros.cuartel = "Todos";
    }
    refreshAll();
  };
  const predioWrap = document.querySelector('#f_predio_chips');
  if(predioWrap && predioWrap.dataset.bound !== '1'){
    predioWrap.dataset.bound = '1';
    predioWrap.addEventListener('click', e=>{
      const btn = e.target.closest('[data-value]');
      if(!btn) return;
      state.filtros.predio = btn.dataset.value || 'Todos';
      if(state.filtros.predio === 'Solo Costos Indirectos'){
        state.filtros.sector = 'Todos';
        state.filtros.cuartel = 'Todos';
      }
      refreshAll();
    });
  }
  const cultivoWrap = document.querySelector('#f_cultivo_chips');
  if(cultivoWrap && cultivoWrap.dataset.bound !== '1'){
    cultivoWrap.dataset.bound = '1';
    cultivoWrap.addEventListener('click', e=>{
      const btn = e.target.closest('[data-cultivo]');
      if(!btn) return;
      const key = btn.dataset.cultivo;
      if(key === 'Todos'){
        state.filtros.predio = 'Todos';
        state.filtros.sector = 'Todos';
        state.filtros.cuartel = 'Todos';
      }else if(key === '__COSTOS_INDIRECTOS__'){
        const isActive = state.filtros.predio === 'Solo Costos Indirectos';
        state.filtros.predio = isActive ? 'Todos' : 'Solo Costos Indirectos';
        state.filtros.sector = 'Todos';
        state.filtros.cuartel = 'Todos';
      }else{
        if(state.filtros.predio === 'Solo Costos Indirectos') state.filtros.predio = 'Todos';
        const selected = state.filtros.sector==="Todos" ? [] : (Array.isArray(state.filtros.sector) ? state.filtros.sector.slice() : [state.filtros.sector]);
        const cleanSelected = selected.filter(s => s !== '__COSTOS_INDIRECTOS__');
        const idx = cleanSelected.indexOf(key);
        if(idx >= 0) cleanSelected.splice(idx,1); else cleanSelected.push(key);
        state.filtros.sector = cleanSelected.length ? cleanSelected : 'Todos';
        if(state.filtros.sector === 'Todos') state.filtros.cuartel = 'Todos';
      }
      refreshAll();
    });
  }
  document.querySelector("#f_cultivo").onchange = e=>{
    state.filtros.sector = e.target.value;
    if(state.filtros.sector !== "Todos" && state.filtros.predio === "Solo Costos Indirectos") state.filtros.predio = "Todos";
    if(state.filtros.sector === "Todos") state.filtros.cuartel = "Todos";
    refreshAll();
  };
  document.querySelector("#f_n1").onchange = e=>{ state.filtros.nivel1 = e.target.value.split(' — ')[0]; refreshAll(); };
  document.querySelector("#f_faena").onchange = e=>{ state.filtros.faena = e.target.value.split(' — ')[0]; refreshAll(); };
  const categoriaWrap = document.querySelector('#f_n1_chips');
  if(categoriaWrap && categoriaWrap.dataset.bound !== '1'){
    categoriaWrap.dataset.bound = '1';
    categoriaWrap.addEventListener('click', e=>{
      const btn = e.target.closest('[data-value]');
      if(!btn) return;
      state.filtros.nivel1 = btn.dataset.value || 'Todos';
      refreshAll();
    });
  }
  document.querySelector("#f_metrica").onchange = e=>{ state.filtros.metrica=e.target.value; refreshAll(); };

  const cuartelChips = document.querySelector('#f_cuartel_chips');
  if(cuartelChips && cuartelChips.dataset.bound !== '1'){
    cuartelChips.dataset.bound = '1';
    cuartelChips.addEventListener('click', e=>{
      const btn = e.target.closest('[data-value]');
      if(!btn) return;
      const key = btn.dataset.value || 'Todos';
      if(key === 'Todos'){
        state.filtros.cuartel = 'Todos';
      }else{
        const selected = state.filtros.cuartel === 'Todos'
          ? []
          : (Array.isArray(state.filtros.cuartel) ? state.filtros.cuartel.slice() : [state.filtros.cuartel]);
        const idx = selected.indexOf(key);
        if(idx >= 0) selected.splice(idx, 1); else selected.push(key);
        state.filtros.cuartel = selected.length ? selected : 'Todos';
      }
      refreshAll();
    });
  }

  // Event listener para dropdown de meses
  const mesDropdown = document.querySelector("#f_mes");
  const mesBtn = mesDropdown.querySelector('.mes-dropdown-btn');
  
  // Toggle dropdown
  mesBtn.addEventListener('click', e=>{
    e.stopPropagation();
    const willOpen = !mesDropdown.classList.contains('open');
    mesDropdown.classList.toggle('open', willOpen);
    mesBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  });
  
  // Cerrar al hacer click fuera
  document.addEventListener('click', e=>{
    if(!mesDropdown.contains(e.target)){
      mesDropdown.classList.remove('open');
      mesBtn.setAttribute('aria-expanded', 'false');
    }
  });
  
  // Cambios en checkboxes
  mesDropdown.addEventListener('change', e=>{
    const checkbox = e.target;
    if(!checkbox.matches('input[type="checkbox"]')) return;
    
    const panel = mesDropdown.querySelector('.mes-dropdown-panel');
    const todosCheck = panel.querySelector('#mes_todos');
    const mesChecks = Array.from(panel.querySelectorAll('input[data-mes]'));
    const noneCheck = panel.querySelector('#mes_none');
    
    if(checkbox.id==='mes_todos'){
      // "Seleccionar todos" toggle
      mesChecks.forEach(c=>c.checked = checkbox.checked);
      state.filtros.mes = "Todos";
      if(noneCheck) noneCheck.checked = false;
    }else if(checkbox.id==='mes_none'){
      mesChecks.forEach(c=>c.checked = false);
      if(todosCheck) todosCheck.checked = false;
      state.filtros.mes = [];
    }else{
      // Checkbox individual
      const checkedMeses = mesChecks.filter(c=>c.checked).map(c=>c.dataset.mes);
      if(checkedMeses.length===mesChecks.length){
        state.filtros.mes = "Todos";
        todosCheck.checked = true;
        if(noneCheck) noneCheck.checked = false;
      }else if(checkedMeses.length===0){
        state.filtros.mes = [];
        todosCheck.checked = false;
        if(noneCheck) noneCheck.checked = true;
      }else{
        state.filtros.mes = checkedMeses;
        todosCheck.checked = false;
        if(noneCheck) noneCheck.checked = false;
      }
    }
    refreshAll();
  });
  document.querySelector("#f_orden").onchange = e=>{ state.filtros.orden=e.target.value==="Asc"?"Asc":"Desc"; refreshAll(); };
  document.querySelector("#d_n1").onchange = e=>{ state.detalle.nivel1 = e.target.value; buildDetalle(); };
  document.querySelector("#d_metrica").onchange = e=>{ state.detalle.metrica=e.target.value; buildDetalle(); };
  document.querySelector("#d_orden").onchange = e=>{ state.detalle.orden=e.target.value; buildDetalle(); };
  const compOrden = document.getElementById('comparativo-orden-by');
  if(compOrden){
    compOrden.value = state.comparativo?.ordenBy || 'v25';
    compOrden.onchange = e=>{
      state.comparativo.ordenBy = e.target.value || 'v25';
      buildComparativo();
    };
  }
}
window.__initDashboardFromRawCSV = initDashboardFromRawCSV;
const btnInv2425 = document.getElementById('btnToggleInv2425');
const resumenStatus = document.getElementById('resumen-status');
const dataStatusToggle = document.getElementById('btnToggleDataStatus');
const dashboardMetaSource = document.getElementById('dashboardMetaSource');
const dashboardMetaGenerated = document.getElementById('dashboardMetaGenerated');
if(btnInv2425){
  syncGlobalInvToggleUI(btnInv2425);
  btnInv2425.onclick = ()=>{
    hideInv2425 = !hideInv2425;
    syncGlobalInvToggleUI(btnInv2425);
    try {
      localStorage.setItem(HIDE_INV_STORAGE_KEY, hideInv2425 ? '1' : '0');
    } catch (e) {
      // Ignorar errores de almacenamiento en navegación privada/restringida
    }
    refreshAll();
  };
}

function setupThemeToggle(){
  const root = document.getElementById('dashboard-higuera-wrapper');
  const btn = document.getElementById('themeToggle');
  const txt = document.getElementById('themeToggleText');
  if(!root || !btn) return;
  let theme = 'light';
  try{
    const stored = localStorage.getItem(THEME_STORAGE_KEY);
    if(stored === 'dark' || stored === 'light') theme = stored;
  }catch(e){ theme = 'light'; }
  const applyTheme = (next)=>{
    root.setAttribute('data-theme', next);
    document.body && document.body.setAttribute('data-dlh-theme', next);
    btn.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
    if(txt) txt.textContent = next === 'dark' ? 'Oscuro' : 'Claro';
    try{ localStorage.setItem(THEME_STORAGE_KEY, next); }catch(e){}
  };
  applyTheme(theme);
  btn.addEventListener('click', ()=>{
    const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    applyTheme(current === 'dark' ? 'light' : 'dark');
  });
}
function getAccordionState(){
  try{
    return JSON.parse(localStorage.getItem(FILTER_ACCORDIONS_STORAGE_KEY) || '{}');
  }catch(e){
    return {};
  }
}
function saveAccordionState(next){
  try{ localStorage.setItem(FILTER_ACCORDIONS_STORAGE_KEY, JSON.stringify(next)); }catch(e){}
}
function setupFilterAccordions(){
  const accordions = Array.from(document.querySelectorAll('.filter-accordion'));
  if(!accordions.length) return;
  const defaults = { ubicacion:true, clasificacion:false, periodo:true, visualizacion:false, opciones:false };
  const stored = getAccordionState();
  accordions.forEach(section=>{
    const key = section.dataset.filterGroup || '';
    const btn = section.querySelector('.filter-group-toggle');
    const body = section.querySelector('.filter-group-body');
    if(!btn || !body) return;
    const isOpen = Object.prototype.hasOwnProperty.call(stored, key) ? !!stored[key] : !!defaults[key];
    section.classList.toggle('is-open', isOpen);
    body.classList.toggle('hidden', !isOpen);
    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    btn.addEventListener('click', ()=>{
      const nextOpen = !section.classList.contains('is-open');
      section.classList.toggle('is-open', nextOpen);
      body.classList.toggle('hidden', !nextOpen);
      btn.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
      const snapshot = getAccordionState();
      snapshot[key] = nextOpen;
      saveAccordionState(snapshot);
    });
  });
}
function setupSidebarToggle(){
  const btn = document.getElementById('btnToggleSidebar');
  const sidebar = document.getElementById('dashboard-sidebar');
  const layout = document.querySelector('.dashboard-layout');
  const root = document.getElementById('dashboard-higuera-wrapper');
  if(!btn || !sidebar || !layout || !root) return;
  let collapsed = false;
  let mobileOpen = false;
  try{ collapsed = localStorage.getItem(SIDEBAR_COLLAPSED_STORAGE_KEY) === '1'; }catch(e){ collapsed = false; }
  const isMobile = ()=> window.matchMedia('(max-width: 980px)').matches;
  const applyState = ()=>{
    const mobile = isMobile();
    if(mobile){
      layout.classList.remove('is-sidebar-collapsed');
      root.classList.remove('sidebar-collapsed');
      root.classList.remove('dashboard-sidebar-collapsed');
      sidebar.classList.remove('is-collapsed');
      sidebar.hidden = !mobileOpen;
      sidebar.setAttribute('aria-hidden', mobileOpen ? 'false' : 'true');
      sidebar.classList.toggle('is-open', mobileOpen);
      root.classList.toggle('dlh-show-collapsed-summary', !mobileOpen);
      btn.textContent = mobileOpen ? 'Cerrar filtros' : 'Mostrar filtros';
      btn.setAttribute('aria-expanded', mobileOpen ? 'true' : 'false');
      return;
    }
    mobileOpen = false;
    sidebar.classList.remove('is-open');
    sidebar.hidden = collapsed;
    sidebar.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
    sidebar.classList.toggle('is-collapsed', collapsed);
    layout.classList.toggle('is-sidebar-collapsed', collapsed);
    root.classList.toggle('sidebar-collapsed', collapsed);
    root.classList.toggle('dashboard-sidebar-collapsed', collapsed);
    root.classList.toggle('dlh-show-collapsed-summary', collapsed);
    btn.textContent = collapsed ? 'Mostrar filtros' : 'Ocultar filtros';
    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  };
  btn.addEventListener('click', ()=>{
    if(isMobile()){
      mobileOpen = !mobileOpen;
      applyState();
      return;
    }
    collapsed = !collapsed;
    try{ localStorage.setItem(SIDEBAR_COLLAPSED_STORAGE_KEY, collapsed ? '1' : '0'); }catch(e){}
    applyState();
  });
  window.addEventListener('resize', applyState);
  applyState();
}
function setDenseMode(tabId){
  const root = document.getElementById('dashboard-higuera-wrapper');
  if(!root) return;
  root.classList.toggle('dense-mode', tabId === 'resumen' || tabId === 'detalle');
}
function setupActionButtons(){
  const btnClear = document.getElementById('btnClearFilters');
  const btnReset = document.getElementById('btnResetView');
  if(btnClear) btnClear.addEventListener('click', ()=> clearFilters(false));
  if(btnReset) btnReset.addEventListener('click', ()=> clearFilters(true));
}
setupThemeToggle();
setupFilterAccordions();
setupSidebarToggle();
setupActionButtons();
if(resumenStatus && dataStatusToggle){
  const comparativoStatus = document.getElementById('comparativo-status');
  let isHidden = true;
  try{
    const stored = localStorage.getItem(RESUMEN_STATUS_STORAGE_KEY);
    isHidden = stored === null ? true : stored === '1';
  }catch(e){
    isHidden = true;
  }
  const applyResumenToggle = (hidden)=>{
    resumenStatus.classList.toggle('is-collapsed', hidden);
    if(comparativoStatus) comparativoStatus.classList.toggle('is-collapsed', hidden);
    dataStatusToggle.textContent = hidden ? 'Mostrar estado' : 'Ocultar estado';
    dataStatusToggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
  };
  applyResumenToggle(isHidden);
  dataStatusToggle.addEventListener('click', ()=>{
    isHidden = !isHidden;
    try{
      localStorage.setItem(RESUMEN_STATUS_STORAGE_KEY, isHidden ? '1' : '0');
    }catch(e){
      // Ignorar errores de almacenamiento
    }
    applyResumenToggle(isHidden);
  });
}
setDenseMode('resumen');
document.querySelector('#gen').textContent = new Date().toISOString().slice(0,16).replace('T',' ');
// CSV data will be loaded from external files
let EMBED_CSV = '';
let EMBED_CSV_2425 = '';
async function loadCsv2526Fallback() {
  const csv2526Url = (typeof dashboardHigueraData !== 'undefined' && dashboardHigueraData.csv2526Url)
    ? dashboardHigueraData.csv2526Url
    : 'data/temporada-2025-26.csv';
  const resp = await fetch(csv2526Url);
  if(!resp.ok) throw new Error('No se pudo cargar fallback CSV 25-26');
  const raw = await resp.text();
  if(!raw || !raw.trim()) throw new Error('Fallback CSV 25-26 vacío');
  EMBED_CSV = raw;
  return raw;
}
// Compatibilidad con template actual (no precargar CSV automáticamente)
async function loadExternalCSVs() {
  return true;
}
function extractApiUrlFromPowerBIFormula(formula){
  const raw = String(formula || '').trim();
  if(!raw) return '';
  const m = raw.match(/Web\.Contents\(\s*"([^"]+)"\s*\)/i);
  if(m && m[1]) return m[1].trim();
  return '';
}

async function initDashboardLive(){
  const apiSourceMode = (typeof dashboardHigueraData !== "undefined" && dashboardHigueraData.apiSourceMode)
    ? dashboardHigueraData.apiSourceMode
    : 'url';
  const powerBIFormula = (typeof dashboardHigueraData !== "undefined" && dashboardHigueraData.apiPowerBIFormula)
    ? dashboardHigueraData.apiPowerBIFormula
    : '';
  const formulaUrl = extractApiUrlFromPowerBIFormula(powerBIFormula);
  const configuredApiUrl = (typeof dashboardHigueraData !== "undefined" && dashboardHigueraData.apiUrl)
    ? dashboardHigueraData.apiUrl
    : '';
  const API_URL = (apiSourceMode === 'powerbi' && formulaUrl) ? formulaUrl : configuredApiUrl;
  const apiProxyUrl = (typeof dashboardHigueraData !== "undefined" && dashboardHigueraData.apiProxyUrl)
    ? dashboardHigueraData.apiProxyUrl
    : '';
  try{
    const requestUrl = apiProxyUrl || API_URL;
    if(!requestUrl){
      throw new Error('URL de API no configurada');
    }
    const controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    const timeoutId = setTimeout(()=>{ if(controller) controller.abort(); }, 15000);
    const resp = await fetch(requestUrl, controller ? {signal: controller.signal} : undefined);
    clearTimeout(timeoutId);
    if(!resp.ok) throw new Error('HTTP ' + resp.status);
    const json = await resp.json();
    const rows = Array.isArray(json) ? json : (Array.isArray(json.data) ? json.data : []);
    const csv = buildCSVFromApi(rows, {
      temporada:"2025-2026",
      predioContains:"HIGUERA",
      razonSocial:"AGRICOLA LA HIGUERA S.A."
    });
    if(!csv || !csv.trim()) throw new Error('API sin filas mapeables para 25-26');
    window.__lastCSV = csv;
    __initDashboardFromRawCSV(csv);
    await ensureRentabilidadData();
    refreshAll();
    const chip = document.getElementById('srcChip');
    if(chip) chip.textContent = (apiSourceMode === 'powerbi' && formulaUrl) ? 'API Agrosmart (Power BI URL)' : 'API Agrosmart (en vivo)';
  }catch(e){
    console.error('No se pudo cargar la API, intentando fallback CSV 25-26', e);
    try{
      const csvFallback = await loadCsv2526Fallback();
      window.__lastCSV = csvFallback;
      __initDashboardFromRawCSV(csvFallback);
      await ensureRentabilidadData();
      refreshAll();
      const chip = document.getElementById('srcChip');
      if(chip) chip.textContent = 'CSV 25-26 (fallback por falla de API)';
    }catch(csvError){
      console.error('Falló también el fallback CSV 25-26', csvError);
      const chip = document.getElementById('srcChip');
      if(chip) chip.textContent = 'Error de carga de datos';
      throw csvError;
    }
  }
}
// Iniciar el dashboard en vivo al cargar la página
// En WordPress, esto se maneja desde el template del shortcode
// initDashboardLive();
function buildCSVFromApi(rows, opts){
  opts = opts || {};
  const temporadaTarget = opts.temporada || null;
  const predioContains = opts.predioContains || null;
  const razonSocialTarget = opts.razonSocial || null;
  const razonSocialContains = opts.razonSocialContains || null;
  if(!rows || !rows.length){
    return "";
  }
  const headerOut = ["TEMPORADA","FECHA","ORIGEN","PREDIO","SECTOR","CUARTEL","FAENA","NIVEL 1","SUPERFICIE REAL (há)","TOTAL CUARTEL"];
  function norm(s){
    return String(s || "")
      .toUpperCase()
      .normalize("NFD").replace(/[\u0300-\u036f]/g,"")
      .replace(/_/g," ")
      .replace(/\s+/g," ")
      .trim();
  }
  const keySet = new Set();
  for(const row of rows){
    if(row && typeof row === 'object'){
      for(const k of Object.keys(row)) keySet.add(k);
    }
  }
  const keys = Array.from(keySet);
  const normKeys = {};
  for(const k of keys) normKeys[k] = norm(k);
  function findKeyByAliases(aliases){
    const aliasNorm = aliases.map(a=>norm(a));
    for(const a of aliasNorm){
      for(const k of keys){ if(normKeys[k]===a) return k; }
    }
    for(const a of aliasNorm){
      for(const k of keys){
        const nk = normKeys[k];
        if(nk.includes(a) || a.includes(nk)) return k;
      }
    }
    return null;
  }
  const map = {
    "TEMPORADA": findKeyByAliases(["TEMPORADA", "TEMP"]),
    "FECHA": findKeyByAliases(["FECHA", "FECHA DOCUMENTO", "FEC"]),
    "ORIGEN": findKeyByAliases(["ORIGEN", "TIPO ORIGEN"]),
    "RAZON SOCIAL": findKeyByAliases(["RAZON SOCIAL", "RAZON_SOCIAL", "EMPRESA", "CLIENTE"]),
    "PREDIO": findKeyByAliases(["PREDIO", "CAMPO"]),
    "SECTOR": findKeyByAliases(["SECTOR", "CULTIVO"]),
    "CUARTEL": findKeyByAliases(["CUARTEL", "LOTE"]),
    "FAENA": findKeyByAliases(["FAENA", "LABOR"]),
    "NIVEL 1": findKeyByAliases(["NIVEL 1", "NIVEL_1", "NIVEL1"]),
    "SUPERFICIE REAL (há)": findKeyByAliases(["SUPERFICIE REAL (HA)", "SUPERFICIE REAL", "SUPERFICIE", "HAS", "HECTAREAS"]),
    "TOTAL CUARTEL": findKeyByAliases(["TOTAL CUARTEL", "TOTAL", "MONTO", "VALOR"])
  };
  const required = ["FECHA", "PREDIO", "SECTOR", "CUARTEL", "FAENA", "NIVEL 1", "TOTAL CUARTEL"];
  const missing = required.filter(k=>!map[k]);
  if(missing.length){
    console.warn('buildCSVFromApi: columnas no detectadas', missing, 'keys:', keys);
    throw new Error('Mapeo incompleto API: ' + missing.join(', '));
  }
  const linesOut = [headerOut.join(';')];
  for(const r of rows){
    if(!r || typeof r !== 'object') continue;
    if(temporadaTarget || predioContains){
      const tempVal = map["TEMPORADA"] ? String(r[map["TEMPORADA"]] || "").trim() : "";
      const predVal = map["PREDIO"] ? String(r[map["PREDIO"]] || "").toUpperCase() : "";
      if(temporadaTarget && tempVal !== temporadaTarget) continue;
      if(predioContains && !predVal.includes(predioContains.toUpperCase())) continue;
    }
    if(razonSocialTarget || razonSocialContains){
      const rsVal = map["RAZON SOCIAL"] ? String(r[map["RAZON SOCIAL"]] || "") : "";
      const rsNorm = norm(rsVal);
      if(razonSocialTarget && rsNorm !== norm(razonSocialTarget)) continue;
      if(razonSocialContains && !rsNorm.includes(norm(razonSocialContains))) continue;
    }
    const line = headerOut.map(h=>{
      const key = map[h];
      const v = key ? (r[key] != null ? r[key] : "") : "";
      const s = String(v).replace(/"/g,'""');
      return '"' + s + '"';
    }).join(';');
    linesOut.push(line);
  }
  return linesOut.join("\n");
}
function showTab(id){ 
  $$('.dashboard-main-tabs .tab[data-tab]').forEach(t=>{
    const active = t.dataset.tab===id;
    t.classList.toggle('active', active);
    t.setAttribute('aria-selected', active ? 'true' : 'false');
    t.setAttribute('tabindex', active ? '0' : '-1');
  });
  $('#panel-resumen').classList.toggle('hidden', id!=='resumen');
  $('#panel-graficos').classList.toggle('hidden', id!=='graficos');
  const pc = $('#panel-comparativo');
  if(pc) pc.classList.toggle('hidden', id!=='comparativo');
  $('#panel-detalle').classList.toggle('hidden', id!=='detalle');
  const nota = $('#nota-resumen');
  if(nota) nota.classList.toggle('hidden', id!=='resumen');
  setDenseMode(id);
  if(id==='comparativo') buildComparativo();
}
function bindRentabilidadTabs(){
  const tabs = $$('.rent-tabs .tab[data-rent-tab]');
  if(!tabs.length) return;
  tabs.forEach(tab=>{
    tab.onclick = async ()=>{
      tabs.forEach(t=>t.classList.remove('active'));
      tab.classList.add('active');
      const isComp = tab.dataset.rentTab === 'comparativo';
      const controls = document.getElementById('rentabilidad-comparativo-controls');
      if(controls) controls.classList.toggle('hidden', !isComp);
      if(isComp){ await ensureRentabilidadComparativo2425(); }
      renderRentabilidadResumen();
    };
  });
  const metric = document.getElementById('rent-metrica');
  const metricChips = document.querySelectorAll('.rent-metrica-chip[data-rent-metrica]');
  if(metric){
    metric.addEventListener('change', ()=>{
      syncRentMetricaChips(metric.value);
      renderRentabilidadResumen();
    });
    syncRentMetricaChips(metric.value);
  }
  metricChips.forEach(chip=>{
    chip.addEventListener('click', ()=>{
      if(!metric) return;
      const nextValue = chip.dataset.rentMetrica || '';
      if(!nextValue || metric.value === nextValue) return;
      metric.value = nextValue;
      syncRentMetricaChips(nextValue);
      renderRentabilidadResumen();
    });
  });
}
$$('.dashboard-main-tabs .tab[data-tab]').forEach((t, idx, tabs)=>{
  t.onclick = ()=> showTab(t.dataset.tab);
  t.addEventListener('keydown', (e)=>{
    if(e.key === 'Enter' || e.key === ' '){
      e.preventDefault();
      showTab(t.dataset.tab);
      return;
    }
    if(e.key === 'ArrowRight' || e.key === 'ArrowLeft'){
      e.preventDefault();
      const delta = e.key === 'ArrowRight' ? 1 : -1;
      const next = tabs[(idx + delta + tabs.length) % tabs.length];
      if(next){
        next.focus();
        showTab(next.dataset.tab);
      }
    }
  });
});
bindRentabilidadTabs();
document.addEventListener('click', (e)=>{
  const chipBtn = e.target.closest('.filter-chip[data-chip-key]');
  if(chipBtn){
    clearFilterChip(chipBtn.dataset.chipKey || '');
    return;
  }
});
document.addEventListener('change', (e)=>{
  if(e.target.name==='gtype'){
    const v=e.target.value;
    $('#chart_bars').classList.toggle('hidden', v!=='barras');
    $('#chart_pie').classList.toggle('hidden', v!=='pie');
  }
});
// Cambiar CSV
const swapBtn = document.getElementById('btnSwap');
const swapInput = document.getElementById('fileSwap');
if(swapBtn && swapInput){
  swapBtn.addEventListener('click', ()=>{ swapInput.click(); });
  swapInput.addEventListener('change', async ()=>{
  const f = swapInput.files && swapInput.files[0]; if(!f) return;
  try{
    const raw = await f.text();
    window.__lastCSV = raw;
    __initDashboardFromRawCSV(raw);
    const chip = document.getElementById('srcChip'); if(chip) chip.textContent = 'CSV local (cargado) — ' + (f.name || '');
  }catch(e){ alert('No se pudo cargar el CSV nuevo: ' + (e?.message || e)); }
  });
}
// Guardar HTML con datos embebidos
async function saveHTMLWithData() { 
  try{
    const json = JSON.stringify(window.__lastCSV || EMBED_CSV);
    let html = document.documentElement.outerHTML;
    html = html.replace(/const EMBED_CSV = [\s\S]*?;\n/, 'const EMBED_CSV = ' + json + ';\n');
    html = html.replace(/id="srcChip">[^<]*/,'id="srcChip">CSV embebido');
    const full = '<!DOCTYPE html>\n' + html;
    const blob = new Blob([full], {type:'text/html'});
    const filename = 'dashboard_la_higuera_25-26_pre_cargado.html';
    if (window.showSaveFilePicker) {
      const handle = await window.showSaveFilePicker({
        suggestedName: filename,
        types: [{ description:'HTML', accept: {'text/html':['.html']} }]
      });
      const writable = await handle.createWritable();
      await writable.write(full);
      await writable.close();
    } else {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href = url; a.download = filename; a.click();
      setTimeout(()=>URL.revokeObjectURL(url), 1000);
    }
    alert('Listo. Se guardó un HTML independiente con el CSV embebido.');
  }catch(e){ alert('No se pudo guardar el HTML: ' + (e?.message || e)); }
}
const btnSave = document.getElementById('btnSave');
if(btnSave) btnSave.addEventListener('click', saveHTMLWithData);
