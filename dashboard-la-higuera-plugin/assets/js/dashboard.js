
const $ = s=>document.querySelector(s);
const $$ = s=>Array.from(document.querySelectorAll(s));
const fmt = n => (n===null || n===undefined || Number.isNaN(n)) ? "—" : Math.round(n).toLocaleString('es-CL');
const pctFmt = x => (x===null||x===undefined||Number.isNaN(x)) ? "—" : Math.round(x*100).toLocaleString('es-CL') + '%';
const strip = s => String(s||'').trim();
const isInversionFaena = v => strip(v||'').toUpperCase()==='INVERSIONES VARIAS';

function splitLines(text){ return text.replace(/\r\n/g,'\n').replace(/\r/g,'\n').split('\n'); }
function detectDelimiter(text){
  const sample = splitLines(text).slice(0,50);
  const c = ch => sample.reduce((a,l)=>a+(l.split(ch).length-1),0);
  return [{d:';',n:c(';')},{d:',',n:c(',')},{d:'\t',n:c('\t')},{d:'|',n:c('|')}].sort((a,b)=>b.n-a.n)[0].d;
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
        TEMPORADA: TEMP, PREDIO, SECTOR, CUARTEL, FAENA, NIVEL_1,
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
        TEMPORADA: TEMP, PREDIO, SECTOR, CUARTEL, FAENA, NIVEL_1,
        VALOR: Number.isFinite(VALOR)? VALOR:0
      });
    }
  }
  return {rows:data, supMap:supLast, totalRows};
}

let comp2425 = {rows:[], supMap:{}};
let comp2425Status = 'idle';
let comp2425ErrorReason = '';
const HIDE_INV_STORAGE_KEY = 'dlh_hide_inv_2425';
const RESUMEN_STATUS_STORAGE_KEY = 'dlh_resumen_status_hidden';
let hideInv2425 = false;
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
  filtros: {predio:"Todos", sector:"Todos", nivel1:"Todos", faena:"Todas", metrica:"VALOR", mes:"Todos", orden:"Desc"},
  detalle: {nivel1:"Todos", metrica:"VALOR", orden:"Desc"},
  comparativo: {meses:"Todos"}
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

function formatStatusValue(value){
  const str = String(value || '').trim();
  return str ? str : '—';
}

function formatComparativoMeses(){
  const meses = getComparativoMesesSeleccionados();
  if(!meses || !meses.length) return 'Todos';
  return meses.join(', ');
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
        <div class="comp-status-row"><span>Inversiones Varias</span><strong>${hideInv2425 ? 'Ocultas' : 'Mostradas'}</strong></div>
      </div>
    </div>
  `;
}

function updateComparativoStatus(){
  const wrap = document.getElementById('comparativo-status');
  const statusCards = buildStatusCardsHtml();
  if(wrap){
    wrap.innerHTML = `<div class="comp-status-title">Estado de datos</div>${statusCards}`;
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
  if(state.filtros.sector!=="Todos") rows = rows.filter(r => strip(r.SECTOR||"")===state.filtros.sector);
  if(state.filtros.nivel1!=="Todos") rows = rows.filter(r => strip(r.NIVEL_1||"")===state.filtros.nivel1);
  if(state.filtros.faena!=="Todas") rows = rows.filter(r => strip(r.FAENA||"")===state.filtros.faena);
  if(hideInv2425) rows = rows.filter(r => !isInversionFaena(r.FAENA));
  if(state.filtros.mes!=="Todos"){
    const fm = state.filtros.mes;
    if(Array.isArray(fm) && fm.length){
      rows = rows.filter(r => fm.includes(r.MES_STR));
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
function populateCombos(){
  const rowsBase = state.data.filter(r=> (state.filtros.predio==="Todos")? true : (state.filtros.predio==="Solo Productivo" ? predioClas(r)==="Productivo" : predioClas(r)==="Indirectos"));
  const sectores = Array.from(new Set(rowsBase.map(r=>strip(r.SECTOR)||"Sin dato"))).sort((a,b)=>a.localeCompare(b,'es'));
  document.querySelector("#f_cultivo").innerHTML = `<option>Todos</option>` + sectores.map(s=>`<option${s===state.filtros.sector?' selected':''}>${s}</option>`).join('');
  const n1Rows = rowsBase.filter(r=>{
    const fm = state.filtros.mes;
    if(fm==="Todos") return true;
    if(Array.isArray(fm) && fm.length) return fm.includes(r.MES_STR);
    return r.MES_STR===fm;
  });
  const arrN1 = metricByKey(n1Rows, r=>strip(r.NIVEL_1)||"—").sort((a,b)=> (state.filtros.orden==="Desc"? (b.v-a.v):(a.v-b.v)));
  document.querySelector("#f_n1").innerHTML = `<option>Todos</option>` + arrN1.map(o=>`<option${o.k===state.filtros.nivel1?' selected':''}>${o.k} — ${fmt(o.v)}</option>`).join('');
  const arrFa = metricByKey(n1Rows, r=>strip(r.FAENA)||"—").sort((a,b)=> (state.filtros.orden==="Desc"? (b.v-a.v):(a.v-b.v)));
  document.querySelector("#f_faena").innerHTML = `<option>Todas</option>` + arrFa.map(o=>`<option${o.k===state.filtros.faena?' selected':''}>${o.k} — ${fmt(o.v)}</option>`).join('');
  
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
  panel.innerHTML = `<div class="mes-dropdown-item todos"><input type="checkbox" id="mes_todos" ${allSelected?'checked':''}><label for="mes_todos">Seleccionar todos</label></div>` +
    meses.map(m=>`<div class="mes-dropdown-item"><input type="checkbox" id="mes_${m}" data-mes="${m}" ${allSelected || selArr.includes(m)?'checked':''}><label for="mes_${m}">${m}</label></div>`).join('');
  
  // Actualizar label del botón
  if(allSelected){
    labelBtn.innerHTML = 'Todos';
  }else if(selArr.length===1){
    labelBtn.innerHTML = selArr[0];
  }else{
    labelBtn.innerHTML = `${selArr.length} meses <span class="mes-count">${selArr.length}</span>`;
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
  let thead = `<thead><tr><th>CUARTEL</th>`; for(const m of meses) thead+=`<th>${m}</th>`; thead+=`<th>TOTAL</th></tr></thead>`;
  tbl.innerHTML = thead + `<tbody></tbody><tfoot></tfoot>`; const tb = tbl.querySelector('tbody');
  for(const c of sorted){
    const vals = meses.map(m=> per[c]?.[m] || 0);
    const totalMes = vals.reduce((a,b)=>a+b,0);
    let metricVals = vals.slice();
    let metricTotal = totalMes;
    if(state.filtros.metrica==="COSTO_HA"){
      const sup = supMap[c]; metricVals = metricVals.map(v=> (sup? v/sup : NaN)); metricTotal = (sup? totalMes/sup : NaN);
    }
    const pct = totalFiltrado>0 ? (totalMes/totalFiltrado) : 0;
    const tr = document.createElement('tr');
    const btn = `<button class="cuartel-btn" data-c="${c}">${c}</button> <span class="pct">${pctFmt(pct)}</span>`;
    let tds = `<td>${btn}</td>`; for(const v of metricVals) tds+=`<td>${fmt(v)}</td>`;
    const totalCell = (state.filtros.mes!=="Todos" && !Array.isArray(state.filtros.mes)) ? metricVals[0] : metricTotal;
    tds += `<td>${fmt(totalCell)}</td>`; tr.innerHTML = tds; tb.appendChild(tr);
    const trd = document.createElement('tr'); trd.className="hidden mini"; const cols = meses.length + 2; trd.innerHTML = `<td colspan="${cols}"><div class="mini-wrap"></div></td>`; tb.appendChild(trd);
  }
  const tf = tbl.querySelector('tfoot'); let totRow = `<tr><td><b>TOTALES</b></td>`;
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
  totRow += `<td>${fmt(totalCell)}</td></tr>`; tf.innerHTML = totRow;
  const cont = document.querySelector("#resumen"); cont.innerHTML = ""; cont.appendChild(tbl);
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
      const tbl2 = document.createElement('table'); let th = `<thead><tr><th>NIVEL 1</th>`; for(const m of mesesMini) th+=`<th>${m}</th>`; th+=`<th>TOTAL</th></tr></thead>`; tbl2.innerHTML = th + `<tbody></tbody>`; const tb2 = tbl2.querySelector('tbody');
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
    if(state.filtros.sector!=="Todos" && strip(r.SECTOR||"")!==state.filtros.sector) return false;
    if(state.filtros.mes!=="Todos"){
      const fm = state.filtros.mes;
      if(Array.isArray(fm) && fm.length){
        if(!fm.includes(r.MES_STR)) return false;
      }else if(fm && fm!=="Todos"){
        if(r.MES_STR!==fm) return false;
      }
    }
    if(hideInv2425 && isInversionFaena(r.FAENA)) return false;
    return true;
  });
  const rows = (state.detalle.nivel1==="Todos")? base : base.filter(r=>strip(r.NIVEL_1||"")===state.detalle.nivel1);
  const cuarteles = Array.from(new Set(rows.map(r=>r.CUARTEL))).filter(Boolean);
  const cont = document.querySelector("#detalle"); cont.innerHTML = "";
  const n1s = Array.from(new Set(base.map(r=>strip(r.NIVEL_1)||"—"))).sort((a,b)=>a.localeCompare(b,'es'));
  document.querySelector("#d_n1").innerHTML = `<option>Todos</option>` + n1s.map(n=>`<option${n===state.detalle.nivel1?' selected':''}>${n}</option>`).join('');
  for(const c of cuarteles){
    const rcu = rows.filter(r=>r.CUARTEL===c);
    const byFaMes = {}; for(const r of rcu){ const f=strip(r.FAENA)||"—"; const m=r.MES_STR||"—"; byFaMes[f]=byFaMes[f]||{}; byFaMes[f][m]=(byFaMes[f][m]||0)+(r.VALOR||0); }
    const faenas = Object.keys(byFaMes).sort((a,b)=>{ const ta=Object.values(byFaMes[a]).reduce((A,B)=>A+B,0); const tb=Object.values(byFaMes[b]).reduce((A,B)=>A+B,0); return state.detalle.orden==="Desc"? (tb-ta):(ta-tb); });
    const box = document.createElement('div'); box.className="card";
    const head=document.createElement('div'); head.className="acc-head"; head.innerHTML=`<span class="acc-caret">▶</span><b>${c}</b>`;
    const body=document.createElement('div'); body.className="acc-body hidden";
    
    const table=document.createElement('table');
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
 let th=`<thead><tr><th>FAENA</th>`; for(const m of meses) th+=`<th>${m}</th>`; th+=`<th>TOTAL</th></tr></thead><tbody></tbody>`; table.innerHTML=th; const tb=table.querySelector('tbody');
    for(const f of faenas){
      let row=`<td>${f}</td>`; let tot=0; for(const m of meses){ const v=byFaMes[f][m]||0; tot+=v; const val=(state.detalle.metrica==="COSTO_HA")?(state.supMap[c]? v/state.supMap[c]:NaN):v; row+=`<td>${fmt(val)}</td>`; }
      const totVal=(state.detalle.metrica==="COSTO_HA")?(state.supMap[c]? tot/state.supMap[c]:NaN):tot; const shown=(state.filtros.mes!=="Todos")?((state.detalle.metrica==="COSTO_HA")?(state.supMap[c]? (byFaMes[f][meses[0]]||0)/state.supMap[c]:NaN):(byFaMes[f][meses[0]]||0)):totVal; row+=`<td>${fmt(shown)}</td>`; const tr=document.createElement('tr'); tr.innerHTML=row; tb.appendChild(tr);
    }
    body.appendChild(table); box.appendChild(head); box.appendChild(body); cont.appendChild(box);
    head.onclick = ()=>{ const wasHidden = body.classList.contains('hidden'); body.classList.toggle('hidden'); head.querySelector('.acc-caret').textContent = wasHidden ? '▼' : '▶'; };
  }
  const exp = document.querySelector("#d_expand"), col = document.querySelector("#d_collapse");
  if(exp){ exp.onclick = ()=> { Array.from(document.querySelectorAll('#detalle .acc-body')).forEach(b=>b.classList.remove('hidden')); Array.from(document.querySelectorAll('#detalle .acc-head .acc-caret')).forEach(c=>c.textContent='▼'); } }
  if(col){ col.onclick = ()=> { Array.from(document.querySelectorAll('#detalle .acc-body')).forEach(b=>b.classList.add('hidden')); Array.from(document.querySelectorAll('#detalle .acc-head .acc-caret')).forEach(c=>c.textContent='▶'); } }
}

function getComparativoMesesSeleccionados(){
  const fm = state.comparativo?.meses;
  if(fm==="Todos" || !Array.isArray(fm) || !fm.length) return null;
  return fm.slice();
}

function renderComparativoMesesControl(rows25, rows24){
  const wrap = document.getElementById("comparativo-meses");
  if(!wrap) return;

  const available = Array.from(new Set([
    ...rows25.map(r=>r.MES_STR).filter(Boolean),
    ...rows24.map(r=>r.MES_STR).filter(Boolean)
  ]));
  sortMesLabels(available);

  if(!available.length){
    state.comparativo.meses = "Todos";
    wrap.innerHTML = '<span class="small">Meses: sin datos</span>';
    return;
  }

  const sel = getComparativoMesesSeleccionados();
  const validSel = sel ? sel.filter(m=>available.includes(m)) : null;
  if(sel && !validSel.length){
    state.comparativo.meses = "Todos";
  }

  const selected = validSel && validSel.length ? validSel : available;
  const selectedSet = new Set(selected);
  const allChecked = selected.length===available.length;

  const items = available.map(m=>`<label class="mes-dropdown-item"><input type="checkbox" data-comp-mes="${m}" ${selectedSet.has(m)?'checked':''}><span>${m}</span></label>`).join('');
  const countText = allChecked ? 'Todos' : `${selectedSet.size} mes(es)`;

  wrap.innerHTML = `<div class="mes-dropdown" id="comp_mes">    <button type="button" class="mes-dropdown-btn">      <span>Meses comparativo</span>      <span class="mes-count">${countText}</span>      <span class="arrow">▼</span>    </button>    <div class="mes-dropdown-panel">      <label class="mes-dropdown-item todos"><input type="checkbox" id="comp_mes_todos" ${allChecked?'checked':''}><span>Todos</span></label>      ${items}    </div>  </div>`;

  const dropdown = wrap.querySelector('#comp_mes');
  const btn = dropdown.querySelector('.mes-dropdown-btn');
  btn.onclick = e=>{ e.stopPropagation(); dropdown.classList.toggle('open'); };

  dropdown.addEventListener('change', e=>{
    const t = e.target;
    if(!t.matches('input[type="checkbox"]')) return;
    const panel = dropdown.querySelector('.mes-dropdown-panel');
    const allBox = panel.querySelector('#comp_mes_todos');
    const mesBoxes = Array.from(panel.querySelectorAll('input[data-comp-mes]'));

    if(t.id==='comp_mes_todos'){
      mesBoxes.forEach(x=>x.checked=t.checked);
      state.comparativo.meses = 'Todos';
    }else{
      const checked = mesBoxes.filter(x=>x.checked).map(x=>x.dataset.compMes);
      if(!checked.length || checked.length===mesBoxes.length){
        state.comparativo.meses = 'Todos';
        allBox.checked = true;
        if(!checked.length) mesBoxes.forEach(x=>x.checked=true);
      }else{
        state.comparativo.meses = checked;
        allBox.checked = false;
      }
    }
    buildComparativo();
  });

  if(!window.__dlhCompMesDocListener){
    document.addEventListener('click', e=>{
      const current = document.getElementById('comp_mes');
      if(current && !current.contains(e.target)) current.classList.remove('open');
    });
    window.__dlhCompMesDocListener = true;
  }
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
    if(filtros.sector!=="Todos") out = out.filter(r => strip(r.SECTOR||"")===filtros.sector);
    if(filtros.nivel1!=="Todos") out = out.filter(r => strip(r.NIVEL_1||"")===filtros.nivel1);
    if(filtros.faena!=="Todas") out = out.filter(r => strip(r.FAENA||"")===filtros.faena);
    if(hideInv2425){
      out = out.filter(r => !isInversionFaena(r.FAENA));
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

  // Base comparativo (sin usar filtro global de meses)
  let rows25 = applyFiltersComparativo(state.data, false);
  let rows24Full = applyFiltersComparativo(comp2425.rows, false);
  let rows24Match = applyFiltersComparativo(comp2425.rows, false);

  renderComparativoMesesControl(rows25, rows24Full);
  const compMeses = getComparativoMesesSeleccionados();
  if(compMeses){
    const mesSet = new Set(compMeses);
    rows25 = rows25.filter(r=>mesSet.has(r.MES_STR));
    rows24Full = rows24Full.filter(r=>mesSet.has(r.MES_STR));
    rows24Match = rows24Match.filter(r=>mesSet.has(r.MES_STR));
  }

  if(!compMeses){
    // Meses que existen en 25-26 (JUN, JUL, ...)
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
  rowsComp.sort((a,b)=>{
    const va = Number.isFinite(a.v25)?a.v25:0;
    const vb = Number.isFinite(b.v25)?b.v25:0;
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
    rowsDetalle.sort((a,b)=>{
      const va = Number.isFinite(a.v25)?a.v25:0;
      const vb = Number.isFinite(b.v25)?b.v25:0;
      return ord2==="Asc" ? va-vb : vb-va;
    });

    const sum24 = rowsDetalle.reduce((a,r)=>a+(Number.isFinite(r.v24)?r.v24:0),0);
    const sum25 = rowsDetalle.reduce((a,r)=>a+(Number.isFinite(r.v25)?r.v25:0),0);
    const diffTot = sum25 - sum24;
    const pctTot = (Number.isFinite(sum24) && Math.abs(sum24)>0) ? (sum25/sum24 - 1) : NaN;

    let inner = '<table><thead><tr><th>Nivel 1</th><th>24-25 (mismos meses)</th><th>25-26</th><th>Diferencia</th><th>% Dif.</th></tr></thead><tbody>';
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


function refreshAll(){
  updateComparativoStatus();
  populateCombos();
  buildResumen();
  buildCharts();
  buildDetalle();
  const activeTab = document.querySelector('.tab.active');
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
  refreshAll();
  document.querySelector("#f_predio").onchange = e=>{ state.filtros.predio=e.target.value; refreshAll(); };
  document.querySelector("#f_cultivo").onchange = e=>{ state.filtros.sector = e.target.value; refreshAll(); };
  document.querySelector("#f_n1").onchange = e=>{ state.filtros.nivel1 = e.target.value.split(' — ')[0]; refreshAll(); };
  document.querySelector("#f_faena").onchange = e=>{ state.filtros.faena = e.target.value.split(' — ')[0]; refreshAll(); };
  document.querySelector("#f_metrica").onchange = e=>{ state.filtros.metrica=e.target.value; refreshAll(); };
  
  // Event listener para dropdown de meses
  const mesDropdown = document.querySelector("#f_mes");
  const mesBtn = mesDropdown.querySelector('.mes-dropdown-btn');
  
  // Toggle dropdown
  mesBtn.addEventListener('click', e=>{
    e.stopPropagation();
    mesDropdown.classList.toggle('open');
  });
  
  // Cerrar al hacer click fuera
  document.addEventListener('click', e=>{
    if(!mesDropdown.contains(e.target)){
      mesDropdown.classList.remove('open');
    }
  });
  
  // Cambios en checkboxes
  mesDropdown.addEventListener('change', e=>{
    const checkbox = e.target;
    if(!checkbox.matches('input[type="checkbox"]')) return;
    
    const panel = mesDropdown.querySelector('.mes-dropdown-panel');
    const todosCheck = panel.querySelector('#mes_todos');
    const mesChecks = Array.from(panel.querySelectorAll('input[data-mes]'));
    
    if(checkbox.id==='mes_todos'){
      // "Seleccionar todos" toggle
      mesChecks.forEach(c=>c.checked = checkbox.checked);
      state.filtros.mes = "Todos";
    }else{
      // Checkbox individual
      const checkedMeses = mesChecks.filter(c=>c.checked).map(c=>c.dataset.mes);
      if(checkedMeses.length===0 || checkedMeses.length===mesChecks.length){
        state.filtros.mes = "Todos";
        todosCheck.checked = true;
        if(checkedMeses.length===0) mesChecks.forEach(c=>c.checked=true);
      }else{
        state.filtros.mes = checkedMeses;
        todosCheck.checked = false;
      }
    }
    refreshAll();
  });

  document.querySelector("#f_orden").onchange = e=>{ state.filtros.orden=e.target.value==="Asc"?"Asc":"Desc"; refreshAll(); };
  document.querySelector("#d_n1").onchange = e=>{ state.detalle.nivel1 = e.target.value; buildDetalle(); };
  document.querySelector("#d_metrica").onchange = e=>{ state.detalle.metrica=e.target.value; buildDetalle(); };
  document.querySelector("#d_orden").onchange = e=>{ state.detalle.orden=e.target.value; buildDetalle(); };
}
window.__initDashboardFromRawCSV = initDashboardFromRawCSV;

const btnInv2425 = document.getElementById('btnToggleInv2425');
const resumenStatus = document.getElementById('resumen-status');
const resumenToggle = document.getElementById('btnToggleResumenStatus');

if(btnInv2425){
  btnInv2425.textContent = hideInv2425
    ? 'Mostrar INVERSIONES VARIAS'
    : 'Ocultar INVERSIONES VARIAS';
  btnInv2425.setAttribute('aria-pressed', hideInv2425 ? 'true' : 'false');
  btnInv2425.classList.toggle('is-active', hideInv2425);

  btnInv2425.onclick = ()=>{
    hideInv2425 = !hideInv2425;
    btnInv2425.textContent = hideInv2425
      ? 'Mostrar INVERSIONES VARIAS'
      : 'Ocultar INVERSIONES VARIAS';
    btnInv2425.setAttribute('aria-pressed', hideInv2425 ? 'true' : 'false');
    btnInv2425.classList.toggle('is-active', hideInv2425);
    try {
      localStorage.setItem(HIDE_INV_STORAGE_KEY, hideInv2425 ? '1' : '0');
    } catch (e) {
      // Ignorar errores de almacenamiento en navegación privada/restringida
    }
    refreshAll();
  };
}

if(resumenStatus && resumenToggle){
  let isHidden = false;
  try{
    isHidden = localStorage.getItem(RESUMEN_STATUS_STORAGE_KEY) === '1';
  }catch(e){
    isHidden = false;
  }

  const applyResumenToggle = (hidden)=>{
    resumenStatus.classList.toggle('is-collapsed', hidden);
    resumenToggle.textContent = hidden ? 'Mostrar' : 'Ocultar';
    resumenToggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
  };

  applyResumenToggle(isHidden);

  resumenToggle.addEventListener('click', ()=>{
    isHidden = !isHidden;
    try{
      localStorage.setItem(RESUMEN_STATUS_STORAGE_KEY, isHidden ? '1' : '0');
    }catch(e){
      // Ignorar errores de almacenamiento
    }
    applyResumenToggle(isHidden);
  });
}

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


async function initDashboardLive(){
  const API_URL = (typeof dashboardHigueraData !== "undefined" && dashboardHigueraData.apiUrl)
    ? dashboardHigueraData.apiUrl
    : "https://app.agrosmart.cl/v1/api/reporte/base_consolidada.php?token=02376e47a4771e34fcba564f88a9d4fbc42a0c40894ebd8e3ba0d60039bd4528";
  try{
    const resp = await fetch(API_URL);
    if(!resp.ok) throw new Error('HTTP ' + resp.status);
    const json = await resp.json();
    const rows = Array.isArray(json) ? json : (Array.isArray(json.data) ? json.data : []);
    const csv = buildCSVFromApi(rows, {
      temporada:"2025-2026",
      predioContains:"HIGUERA",
      razonSocial:"AGRICOLA LA HIGUERA S.A."
    });
    window.__lastCSV = csv;
    __initDashboardFromRawCSV(csv);
    const chip = document.getElementById('srcChip');
    if(chip) chip.textContent = 'API Agrosmart (en vivo)';
  }catch(e){
    console.error('No se pudo cargar la API, intentando fallback CSV 25-26', e);
    try{
      const csvFallback = await loadCsv2526Fallback();
      window.__lastCSV = csvFallback;
      __initDashboardFromRawCSV(csvFallback);
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

  const sample = rows[0] || {};
  const keys = Object.keys(sample);

  function norm(s){
    return String(s || "")
      .toUpperCase()
      .normalize("NFD").replace(/[\u0300-\u036f]/g,"")
      .replace(/_/g," ")
      .replace(/\s+/g," ")
      .trim();
  }

  const normKeys = {};
  for(const k of keys){
    normKeys[k] = norm(k);
  }

  function findKey(label){
    const target = norm(label).replace("(HA)","HA");
    // exact
    for(const k of keys){
      if(normKeys[k] === target) return k;
    }
    // contiene / contenido
    for(const k of keys){
      const nk = normKeys[k];
      if(nk.includes(target) || target.includes(nk)) return k;
    }
    return null;
  }

  const map = {};
  map["TEMPORADA"]             = findKey("TEMPORADA");
  map["FECHA"]                 = findKey("FECHA");
  map["ORIGEN"]                = findKey("ORIGEN");
  map["RAZON SOCIAL"]          = findKey("RAZON SOCIAL");
  map["PREDIO"]                = findKey("PREDIO");
  map["SECTOR"]                = findKey("SECTOR");
  map["CUARTEL"]               = findKey("CUARTEL");
  map["FAENA"]                 = findKey("FAENA");
  map["NIVEL 1"]               = findKey("NIVEL 1");
  map["SUPERFICIE REAL (há)"]  = findKey("SUPERFICIE REAL");
  map["TOTAL CUARTEL"]         = findKey("TOTAL CUARTEL");

  const linesOut = [];
  linesOut.push(headerOut.join(";"));

  for(const r of rows){
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
    }).join(";");
    linesOut.push(line);
  }

  return linesOut.join("\n");
}

function showTab(id){ 
  $$('.tab').forEach(t=>t.classList.toggle('active', t.dataset.tab===id)); 
  $('#panel-resumen').classList.toggle('hidden', id!=='resumen');
  $('#panel-graficos').classList.toggle('hidden', id!=='graficos');
  const pc = $('#panel-comparativo');
  if(pc) pc.classList.toggle('hidden', id!=='comparativo');
  $('#panel-detalle').classList.toggle('hidden', id!=='detalle');
  const nota = $('#nota-resumen');
  if(nota) nota.classList.toggle('hidden', id!=='resumen');

  if(id==='comparativo') buildComparativo();
}
$$('.tab').forEach(t=> t.onclick = ()=> showTab(t.dataset.tab));
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
