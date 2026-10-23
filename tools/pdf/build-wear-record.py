#!/usr/bin/env python3
"""Build the domestic one-page printable Chinese AcroForm (reportlab + pypdf)."""
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from pathlib import Path
import argparse
parser=argparse.ArgumentParser()
parser.add_argument('--output', required=True)
args=parser.parse_args()
final=Path(args.output)
final.parent.mkdir(parents=True,exist_ok=True)
pdfmetrics.registerFont(TTFont('CN','/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf'))
out=final.with_suffix('.base.pdf')
c=canvas.Canvas(str(out),pagesize=A4)
c.setTitle('SockSpecs 袜子穿着与耐用性记录表');c.setAuthor('SockSpecs')
W,H=A4

def mixed_width(s,size):
 return sum(pdfmetrics.stringWidth(ch,'Helvetica' if ord(ch)<256 else 'CN',size) for ch in s)
def draw_mixed(x,y,s,size):
 c.setFillGray(.12)
 for ch in s:
  font='Helvetica' if ord(ch)<256 else 'CN'
  c.setFont(font,size);c.drawString(x,y,ch);x+=pdfmetrics.stringWidth(ch,font,size)
def text(x,y,s,size=10):
 draw_mixed(x*mm,H-y*mm,s,size)
def line(x1,y1,x2,y2,gray=.65,width=.45):
 c.setStrokeGray(gray);c.setLineWidth(width);c.line(x1*mm,H-y1*mm,x2*mm,H-y2*mm)
def box(x,y,w,h):
 c.setStrokeGray(.45);c.setLineWidth(.5);c.rect(x*mm,H-(y+h)*mm,w*mm,h*mm)
def check(x,y,label):
 box(x,y-2.8,2.6,2.6);text(x+4,y,label,9)

text(12,17,'袜子穿着与耐用性记录表',19)
text(155,17,'索克派 SockSpecs',10)
text(12,24,'可直接填写并保存，也可打印手写。每款一张，每双单独编号。',8.5)
text(153,24,'https://sockspecs.cn',10)
line(12,28,198,28,.2,.9)
text(12,34,'订单号：',9);line(28,35,78,35)
text(84,34,'下单邮箱／账户：',9);line(116,35,198,35)
text(12,43,'款式／型号：');line(35,44,92,44)
text(99,43,'样品编号：');line(119,44,151,44)
text(158,43,'记录人：');line(174,44,198,44)
text(12,51,'颜色／尺码／筒高：');line(47,52,125,52)
text(132,51,'平时鞋码：');line(153,52,198,52)
text(12,59,'常用鞋款：');line(33,60,109,60)
text(116,59,'开始日期：');line(137,60,165,60)
text(169,59,'续页：');line(182,60,198,60)
text(12,67,'开始状态：',8.6);line(33,68,74,68)
text(78,67,'已穿：',8.6);text(105,67,'次',8.6);text(110,67,'共：',8.6);text(143,67,'小时',8.6);text(157,67,'已洗：',8.6);text(193,67,'次',8.6)

xs=[12,26,46,72,84,108,150,198]
top=72;hh=13;rh=16
c.setFillGray(.94);c.rect(12*mm,H-(top+hh)*mm,186*mm,hh*mm,fill=1,stroke=0)
headers=[['日期','月／日'],['活动类型','运动／日常'],['时长／距离','分钟／公里'],['已洗','累计次'],['使用条件','气温／鞋／场地'],['穿着感受','触感、闷湿、滑动、摩擦'],['状态与变化','起球、磨薄、破洞及位置']]
for i,ls in enumerate(headers):
 center=(xs[i]+xs[i+1])/2
 for j,s in enumerate(ls):
  size=9.3 if j==0 else (7.6 if i>=4 else 8)
  draw_mixed(center*mm-mixed_width(s,size)/2,H-(top+5+j*4.6)*mm,s,size)
for x in xs:line(x,top,x,top+hh+8*rh,.45,.5)
for r in range(10):
 y=top if r==0 else top+hh+(r-1)*rh
 line(12,y,198,y,.45,.5)
for r in range(8):
 y=top+hh+r*rh
 text(48,y+6,'时：',8);text(48,y+13,'距：',8)

text(12,219,'填写提示：没留意可留空；无变化写「无」。记录闷湿、摩擦、起球、松垮等变化的部位，方便时拍照。',8.3)
text(12,225,'活动可填跑步、徒步、乒乓球、羽毛球等；时长按分钟填，距离按需填，已洗为穿着前累计次数。',8.3)
line(12,230,198,230,.2,.7)
text(12,237,'阶段小结',11)
text(43,237,'继续使用',9);text(73,237,'停止使用',9);text(100,237,'截至：');line(113,238,138,238);text(141,237,'累计使用：',9);text(172,237,'次／',9);text(191,237,'小时',9)
text(12,246,'最满意／最不满意：',9);line(47,247,198,247)
text(12,255,'洗后变化／停止使用原因：',9);line(59,256,198,256)
text(12,264,'首次变化（累计使用次数）：',8.5);text(61,264,'起球',8.5);text(84,264,'次；',8.5);text(92,264,'磨薄',8.5);text(117,264,'次；',8.5);text(125,264,'破洞／开线',8.5);text(161,264,'次',8.5);text(167,264,'（未出现可留空）',8)
line(12,271,198,271,.65,.45)
text(12,277,'按平时运动或日常安排试穿，不必增加强度或时长；不适时停止。续页沿用编号，累计数不归零。',8.3)
text(12,283,'提交至 feedback@sockspecs.cn；注明订单号。记录仅供内部审核，公开展示需另行征得同意。',8.1)
c.showPage();c.save()

# Embed a full Unicode font for values entered after the PDF is created.
# Static page labels stay subsetted; field values support both Chinese and Latin.
from reportlab.pdfbase.ttfonts import TTFontFile, TTFontMaker
from pypdf import PdfReader, PdfWriter
from pypdf.generic import (DictionaryObject as D, NameObject as N, ArrayObject as A,
                          NumberObject as I, FloatObject as F, TextStringObject as S,
                          BooleanObject as B, DecodedStreamObject)
from io import BytesIO
import struct
w=PdfWriter();w.clone_document_from_reader(PdfReader(out))
def obj(values): return D({N(k):v for k,v in values.items()})
def stream(data):
 x=DecodedStreamObject();x.set_data(data);return w._add_object(x.flate_encode())
face=TTFontFile('/usr/share/fonts/truetype/arphic/uming.ttc',subfontIndex=0)
maker=TTFontMaker()
for tag in face.table:
 if tag not in ('DSIG','EBDT','EBLC','BDF ','FFTM'):
  maker.add(tag,face.get_table(tag))
fontdata=maker.makeStream()
checkfont=TTFontFile(BytesIO(fontdata))
assert all(checkfont.charToGlyph.get(ord(x)) for x in 'A1袜跑步羽毛球')
fontfile=DecodedStreamObject();fontfile.set_data(fontdata)
fontfile[N('/Length1')]=I(len(fontdata))
fontref=w._add_object(fontfile.flate_encode())
desc=w._add_object(obj({'/Type':N('/FontDescriptor'),'/FontName':N('/ARPLMingCN'),
 '/Flags':I(4),'/FontBBox':A([F(v) for v in face.bbox]),'/ItalicAngle':I(0),
 '/Ascent':F(face.ascent),'/Descent':F(face.descent),'/CapHeight':F(face.capHeight),
 '/StemV':I(80),'/FontFile2':fontref}))
glyphmap=bytearray(65536*2)
for code,gid in face.charToGlyph.items():
 if code<65536:struct.pack_into('>H',glyphmap,code*2,gid)
widths=A()
for code,width in sorted(face.charWidths.items()):
 if code<65536 and round(width)!=1000:
  widths.extend([I(code),A([F(width)])])
cid=w._add_object(obj({'/Type':N('/Font'),'/Subtype':N('/CIDFontType2'),'/BaseFont':N('/ARPLMingCN'),
 '/CIDSystemInfo':obj({'/Registry':S('Adobe'),'/Ordering':S('Identity'),'/Supplement':I(0)}),
 '/FontDescriptor':desc,'/DW':I(1000),'/W':widths,'/CIDToGIDMap':stream(bytes(glyphmap))}))
cmap=b'''/CIDInit /ProcSet findresource begin
12 dict begin begincmap
/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def
/CMapName /SockSpecsUnicode def /CMapType 2 def
1 begincodespacerange
<0000> <FFFF>
endcodespacerange
1 beginbfrange
<0000> <FFFF> <0000>
endbfrange
endcmap CMapName currentdict /CMap defineresource pop end end'''
font=w._add_object(obj({'/Type':N('/Font'),'/Subtype':N('/Type0'),'/BaseFont':N('/ARPLMingCN'),
 '/Encoding':N('/Identity-H'),'/DescendantFonts':A([cid]),'/ToUnicode':stream(cmap)}))
form=obj({'/Fields':A(),'/DR':obj({'/Font':obj({'/CNForm':font})}),
 '/DA':S('/CNForm 9 Tf 0 g'),'/NeedAppearances':B(False)})
w._root_object[N('/AcroForm')]=w._add_object(form)
page=w.pages[0];page[N('/Annots')]=A();page[N('/Tabs')]=N('/R')
field_names=[]
def field(name,label,x,y,width,height,size=9,multi=False,maxlen=60):
 rect=A([F(x*mm),F(H-(y+height)*mm),F((x+width)*mm),F(H-y*mm)])
 # Empty appearance is explicit; viewers regenerate it when the value changes.
 appearance=DecodedStreamObject();appearance.set_data(b'q Q')
 appearance.update(obj({'/Type':N('/XObject'),'/Subtype':N('/Form'),
  '/BBox':A([I(0),I(0),F(width*mm),F(height*mm)]),
  '/Resources':obj({'/Font':obj({'/CNForm':font})})}))
 item=obj({'/Type':N('/Annot'),'/Subtype':N('/Widget'),'/FT':N('/Tx'),'/T':S(name),'/TU':S(label),
  '/Rect':rect,'/F':I(4),'/Ff':I(4096 if multi else 0),'/DA':S(f'/CNForm {size} Tf 0 g'),
  '/V':S(''),'/DV':S(''),'/MaxLen':I(maxlen),'/P':page.indirect_reference,
  '/Border':A([I(0),I(0),I(0)]),'/AP':obj({'/N':w._add_object(appearance)})})
 ref=w._add_object(item);form['/Fields'].append(ref);page['/Annots'].append(ref);field_names.append(name)
# Top metadata fields. Coordinates use millimetres from the top-left.
for spec in [
 ('order','订单号',28,29,50,6,10,False,32),
 ('account','下单邮箱或账户',116,29,82,6,9,False,70),
 ('model','商品款式或型号',35,38,57,6,10,False,30),
 ('sample','样品编号',119,38,32,6,10,False,16),
 ('recorder','记录人',174,38,24,6,10,False,10),
 ('variant','颜色、尺码、筒高',47,46,78,6,10,False,35),
 ('shoe_size','平时鞋码',153,46,45,6,10,False,20),
 ('shoe','常用鞋款',33,54,76,6,10,False,35),
 ('start_date','开始日期',137,54,28,6,9,False,10),
 ('sheet','续页编号',182,54,16,6,10,False,4),
 ('initial_state','开始时状态，例如新袜或已使用',33,62,41,6,9,False,18),
 ('initial_uses','开始时已穿次数',90,62,14,6,9,False,5),
 ('initial_hours','开始时已穿小时数',120,62,22,6,9,False,8),
 ('initial_washes','开始时已洗次数',170,62,22,6,9,False,5)]:field(*spec)
for r in range(8):
 y=85+16*r
 field(f'r{r+1}_date',f'第{r+1}次记录：日期',13,y+1,12,14,8.5,False,5)
 field(f'r{r+1}_activity',f'第{r+1}次记录：活动',27,y+1,18,14,9,True,12)
 field(f'r{r+1}_minutes',f'第{r+1}次记录：分钟',54,y+1,17,6,8.5,False,8)
 field(f'r{r+1}_km',f'第{r+1}次记录：公里',54,y+8,17,6,8.5,False,8)
 field(f'r{r+1}_washes',f'第{r+1}次记录：累计洗涤次数',73,y+1,10,14,9,False,4)
 field(f'r{r+1}_conditions',f'第{r+1}次记录：使用条件',85,y+1,22,14,8.5,True,24)
 field(f'r{r+1}_comfort',f'第{r+1}次记录：穿着感受',109,y+1,40,14,8.5,True,42)
 field(f'r{r+1}_changes',f'第{r+1}次记录：状态与变化',151,y+1,46,14,8.5,True,48)
# Mutually exclusive, printable continue/stop radio buttons.
def radio_appearance(selected):
 s=DecodedStreamObject();s.set_data(b'0 G 0.5 w 0.5 0.5 6.4 6.4 re S'+(b' 0 g 1.8 1.8 3.8 3.8 re f' if selected else b''))
 s.update(obj({'/Type':N('/XObject'),'/Subtype':N('/Form'),'/BBox':A([I(0),I(0),F(7.4),F(7.4)])}))
 return w._add_object(s)
parent=obj({'/FT':N('/Btn'),'/T':S('use_status'),'/TU':S('阶段小结：继续使用或停止使用'),'/Ff':I(32768),'/Kids':A(),'/V':N('/Off')})
parentref=w._add_object(parent);form['/Fields'].append(parentref)
for x,value in [(39,'continue'),(69,'stop')]:
 widget=obj({'/Type':N('/Annot'),'/Subtype':N('/Widget'),'/Rect':A([F(x*mm),F(H-237*mm),F((x+2.6)*mm),F(H-234.4*mm)]),
  '/Parent':parentref,'/P':page.indirect_reference,'/F':I(4),'/AS':N('/Off'),
  '/AP':obj({'/N':obj({'/Off':radio_appearance(False),'/'+value:radio_appearance(True)})})})
 ref=w._add_object(widget);parent['/Kids'].append(ref);page['/Annots'].append(ref)
for spec in [
 ('end_date','小结截至日期',113,232,25,6,8.5,False,10),
 ('total_uses','累计使用次数',161,232,13,6,9,False,5),
 ('total_hours','累计使用小时数',182,232,8,6,9,False,5),
 ('summary','最满意或最不满意',47,241,151,6,9,False,46),
 ('wash_summary','洗后变化或停止使用原因',59,250,139,6,9,False,42),
 ('pilling_at','首次起球时累计使用次数',70,259,13,6,9,False,4),
 ('thinning_at','首次磨薄时累计使用次数',103,259,13,6,9,False,4),
 ('hole_at','首次破洞或开线时累计使用次数',147,259,13,6,9,False,4)]:field(*spec)
w.add_metadata({'/Title':'索克派袜子穿着与耐用性记录表（可填写）','/Author':'SockSpecs','/Subject':'可填写并保存的 A4 中文记录表；每款一张，连续记录八次。'})
with final.open('wb') as f:w.write(f)
r=PdfReader(final)
assert len(r.pages)==1 and len(r.get_fields())==len(field_names)+1
assert len(r.pages[0]['/Annots'])==len(field_names)+2
assert not r.is_encrypted
out.unlink()
print(f'{final.resolve()} — {len(r.get_fields())} fields, {final.stat().st_size} bytes')
