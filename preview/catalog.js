window.facetasCatalog = [
  {id:1,name:'The Form Chair',description:'Solid oak · Natural finish',price:245,category:'1',material:'1',color:'1',image:'chair',badge:'BESTSELLER',stock:true},
  {id:2,name:'Terra Ceramic Plates',description:'Hand-glazed · Soft blue',price:48,category:'3',material:'2',color:'2',image:'vase',badge:'',stock:true},
  {id:3,name:'Arc Table Lamp',description:'Sculptural light · Warm white',price:129,category:'2',material:'3',color:'3',image:'lamp',badge:'NEW ARRIVAL',stock:true},
  {id:4,name:'Sunday Linen Sofa',description:'Linen blend · Olive',price:890,category:'1',material:'4',color:'4',image:'sofa',badge:'',stock:true},
  {id:5,name:'Atelier Side Table',description:'Painted wood · Simple lines',price:395,category:'1',material:'1',color:'1',image:'table',badge:'',stock:true},
  {id:6,name:'Gather Bud Vases',description:'Ceramic pair · Soft white',price:65,category:'3',material:'2',color:'3',image:'ceramic',badge:'CONSIDERED DESIGN',stock:true},
  {id:7,name:'Cloud Linen Cushion',description:'Relaxed linen · Oat',price:42,category:'4',material:'4',color:'2',image:'linen',badge:'',stock:true},
  {id:8,name:'Still Life Ceramic Set',description:'Artisan ceramics · Chalk',price:64,category:'3',material:'2',color:'3',image:'bowl',badge:'HANDCRAFTED',stock:true},
  {id:9,name:'The Pause Lounge Chair',description:'Upholstered · Dusty rose',price:320,category:'1',material:'4',color:'5',image:'lounge',badge:'',stock:true},
  {id:10,name:'Everyday Woven Throw',description:'Soft cotton · Natural',price:78,category:'4',material:'5',color:'2',image:'throw',badge:'',stock:false},
  {id:11,name:'The Quiet Corner',description:'Solid wood · Warm oak',price:185,category:'1',material:'1',color:'1',image:'stool',badge:'SMALL SPACES',stock:true},
  {id:12,name:'Glow Reading Light',description:'Brushed metal · Soft glow',price:96,category:'2',material:'3',color:'1',image:'pendant',badge:'',stock:false},
];
const weights = ['15','2','5','80','30','2','2','5','20','1','10','3'];
window.facetasCatalog.forEach((product,index) => { product.weight = weights[index]; });
window.facetasGroups = [
  {id:'f1',label:'Category',options:[{id:'1',label:'Furniture'},{id:'2',label:'Lighting'},{id:'3',label:'Objects & décor'},{id:'4',label:'Textiles'}]},
  {id:'a1',label:'Color',options:[{id:'1',label:'Warm oak',color:'#b69b76'},{id:'2',label:'Sand',color:'#d7ceb8'},{id:'3',label:'Chalk',color:'#efeee5'},{id:'4',label:'Olive',color:'#7c8361'},{id:'5',label:'Dusty rose',color:'#c29792'}]},
  {id:'f3',label:'Weight',options:['1','2','3','5','10','15','20','30','80'].map(value => ({id:value,label:`${value} kg`}))},
  {id:'f2',label:'Material',options:[{id:'1',label:'Wood'},{id:'2',label:'Ceramic'},{id:'3',label:'Metal & moulded'},{id:'4',label:'Linen'},{id:'5',label:'Cotton'}]},
];
window.facetasEmpty = () => ({groups:{},min:null,max:null,stock:false});
window.facetasMatches = (p,s) => Object.entries(s.groups).every(([key,ids]) => ids.includes(({f1:p.category,a1:p.color,f2:p.material,f3:p.weight})[key])) && (s.min === null || p.price >= s.min) && (s.max === null || p.price <= s.max) && (!s.stock || p.stock);
let initial = window.facetasEmpty();
try {
  const raw = new URL(location.href).searchParams.get('q');
  if (raw?.startsWith('fd1.')) {
    const parsed = JSON.parse(atob(raw.slice(4).replace(/-/g,'+').replace(/_/g,'/')));
    if (parsed && parsed.groups && typeof parsed.groups === 'object') {
      for (const group of window.facetasGroups) {
        const selected = Array.isArray(parsed.groups[group.id]) ? parsed.groups[group.id].filter(id => group.options.some(o => o.id === id)) : [];
        if (selected.length) initial.groups[group.id] = selected;
      }
      for (const key of ['min','max']) if (typeof parsed[key] === 'number' && Number.isFinite(parsed[key]) && parsed[key] >= 0) initial[key] = parsed[key];
      initial.stock = parsed.stock === true;
    }
  }
} catch (_) { /* Malformed links use the unfiltered collection. */ }
window.facetasInitial = initial;
const config = {
  state:initial,groups:window.facetasGroups.map(group => ({...group,options:group.options.map(option => ({...option,count:window.facetasCatalog.filter(p => window.facetasMatches(p,{...initial,groups:{...initial.groups,[group.id]:[option.id]}})).length}))})),
  bounds:{min:0,max:1000},currency:'USD',locale:'en-US',priceVisible:true,
  labels:{filters:'Filters',refine:'MAKE IT YOURS',intro:'A few thoughtful filters. The perfect find.',close:'Close filters',clear:'Clear all',apply:'Show results',price:'Price range',min:'Minimum price',max:'Maximum price',stock:'In stock only',stockHint:'Ready for a new home',search:'Search options',more:'Show all options',less:'Show fewer options',remove:'Remove filter',selected:'selected',empty:'No matching options',invalid:'Enter a valid price range.'},
};
document.querySelector('#preview-filters').dataset.fdConfig = JSON.stringify(config);
