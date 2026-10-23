"""Convert the reviewed, read-only source export into a Chinese import manifest."""
import json, pathlib, re, hashlib
root = pathlib.Path(__file__).resolve().parents[2]
source = root / '.local/source-products-live.json'
x = json.loads(source.read_text())
colors = {'White / Blue':'白蓝','White / Gray':'白灰','Qing Gray':'青灰','Royal Blue':'宝蓝','Off-white':'米白','Black':'黑色','Blue':'蓝色','Brown':'棕色','Gray':'灰色','Green':'绿色','Lilac':'丁香紫','Orange':'橙色','Pink':'粉色','Purple':'紫色','Beige':'米色'}
copy = {
'X109':('缓震跑步袜','袜底带缓冲结构，搭配网眼区域和左右脚标识。适合跑步、训练及日常运动穿着。',['running','everyday']),
'X112':('合成纤维运动中筒袜','纹理袜面搭配缓冲袜底与足弓弹力带。适合跑步、健身和日常运动穿着。',['running','everyday']),
'364':('日常羊毛混纺中筒袜','羊毛混纺材质，采用同色提花图案与足弓弹力带。适合日常穿着、散步和轻徒步。',['everyday','hiking']),
'365':('毛圈缓冲羊毛混纺中筒袜','羊毛混纺材质，搭配毛圈缓冲与同色提花图案。适合步行、徒步及户外活动。',['hiking']),
'X70':('低帮缓冲运动袜','棉混纺低帮袜，带缓冲袜底、贴合袜口和后跟提耳。适合日常穿着、运动，以及偏好低帮袜的场景。',['everyday'])}
notes = {
'Catalog garment dimension, not a foot/shoe measurement. Measurement endpoints await sample confirmation. The catalog height must not be interpreted as shaft length above the heel.':'产品手册中的袜子平铺尺寸，不是脚长或鞋码。测量起止位置仍待样品确认，手册标注的高度不能理解为脚跟上方的袜筒长度。',
'Manufacturer catalog and product details. This is a declaration, not an independently measured result.':'来自厂家产品手册及详情页，为厂家声明，非独立实测结果。',
'Manufacturer declares 70% combed cotton, 25% nylon and 5% elastane. No laboratory composition report supplied.':'厂家声明成分为70%精梳棉、25%锦纶、5%氨纶，尚未提供该款的实验室成分报告。',
'Manufacturer workbook net weight of one pair. The operator confirmed that packaging is excluded on 2026-10-18. This is a manufacturer declaration, not an independent sample weighing.':'厂家产品手册中一双袜子的净重。2026-10-18已确认不含包装，为厂家声明，尚未独立称量样品。',
'Original listing dimension in cm converted exactly to mm (×10). The measured object and packaging inclusion are unconfirmed. Not a sock-shape dimension.':'详情页原始厘米数值按×10换算为毫米。测量对象及是否包含包装尚未确认，不能用作袜子的形态尺寸。'}
titles={'Manufacturer catalog and product details':'厂家产品手册与详情页','Manufacturer product-listing size/color table':'厂家详情页颜色与尺码表','Manufacturer workbook size row':'厂家产品手册尺码记录'}
scope_replacements={'Model-wide construction declaration; all offered sizes/colors':'该型号工艺声明；适用于全部已列颜色和尺码','Whole sock; all offered sizes/colors':'整袜；适用于全部已列颜色和尺码','Whole-sock model declaration; all offered sizes/colors':'整袜成分声明；适用于全部已列颜色和尺码','listing package/item dimensions':'详情页商品或包装尺寸','one pair, excluding packaging':'一双，不含包装','One size':'均码','Submitted black sample: excluding heel and toe; size/batch unconfirmed':'送检黑色样品：不含袜跟及袜头；尺码与批次未确认','Submitted black/gray sample: excluding heel and toe; size/batch unconfirmed':'送检黑灰色样品：不含袜跟及袜头；尺码与批次未确认','Submitted blue sample: excluding heel and toe; size/batch unconfirmed':'送检蓝色样品：不含袜跟及袜头；尺码与批次未确认','Submitted light gray sample: sole, excluding heel and toe; size/batch unconfirmed':'送检浅灰色样品：袜底，不含袜跟及袜头；尺码与批次未确认'}
def scope(s):
 for a,b in scope_replacements.items():s=s.replace(a,b)
 for a,b in colors.items():s=s.replace(a,b)
 return s
products=[]
for p in x['products']:
 model=p['model'];name,description,tags=copy[model];image=x['images'][str(p['image_id'])]
 out={'source_id':p['id'],'model':model,'sku':p['sku'],'slug':p['slug'],'name':name+' — '+p['sku'],'description':'<p>'+description+'</p>','tags':tags,'image':{'file':'images/ss-001-'+model.lower()+'.webp','sha256':image['sha256'],'source_url':image['url']},'variations':[], 'claims':[]}
 for v in p['variations']:
  out['variations'].append({'source_id':v['id'],'sku':v['sku'],'attributes':{'color':colors[v['attributes']['color']],'size':v['attributes']['size'].replace('One size','均码').replace('(','(EU ')}})
 for c in p['claims']:
  c=dict(c);c['source_id']=c.pop('id');c['source_variation_id']=c.pop('wc_variation_id');c['value']=c.pop('value_num') or c['value_enum'];c.pop('value_enum');c['size_scope']=scope(c['size_scope'])
  if c['note'].startswith('Report '):
   report=re.match(r'Report ([^,]+),',c['note'])[1]
   note='供应商提供的样品报告 '+report+'。结果仅适用于送检样品及检测部位，不代表所有颜色、尺码或批次。样品尺码与生产批次未注明，仅供产品资料参考；报告真实性及实验室资质尚未经独立核验。'
   if 'sheep wool' in c['note']:note+='报告鉴别的是绵羊毛，未确认美利奴品种或纤维细度。'
   c['note']=note
  else:c['note']=notes[c['note']]
  ev=c['evidence'];ev['public_title']=titles.get(ev['public_title'],ev['public_title'].replace('Supplier-provided sample report','供应商提供的样品报告'))
  # Do not imply that the private source archive was copied into the new site.
  match=re.search(r'Stored source SHA-256: [a-f0-9]{64}',ev['note']);assert match and ev['visibility']=='private' and not ev['attachment_id'] and not ev['public_url']
  ev['note']=match[0]+'; 原始资料保存在国际站的私有来源档案中，未复制到国内站媒体库。国际站已发布记录：'+str(c['source_id'])
  ev['attachment_id']=''
  c['sample_count']=c['sample_count'] if c['sample_count'] is not None else ''
  out['claims'].append(c)
 products.append(out)
result={'version':1,'source':'https://sockspecs.com','export_sha256':hashlib.sha256(source.read_bytes()).hexdigest(),'products':products}
(root/'tools/deploy/catalog/products.zh-CN.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
print('Prepared Chinese products:',len(products),'variations:',sum(len(p['variations']) for p in products),'records:',sum(len(p['claims']) for p in products))
print('Remaining scope words:',sorted({c['size_scope'] for p in products for c in p['claims'] if re.search('[a-z]{3}',c['size_scope'])}))
