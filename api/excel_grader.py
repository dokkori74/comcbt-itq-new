#!/usr/bin/env python3
"""
ITQ Excel 범용 채점 엔진 v4
- 1~8회차 모두 지원
- 정답 파일을 기준으로 자동 감지하여 채점
"""
import sys,json,os,re,zipfile
from xml.etree import ElementTree as ET
from html import unescape

ITEMS=[
    {"code":"1A","name":"[제1작업] 기본 데이터 (B5:F12 셀 값)","point":20},
    {"code":"1B","name":"[제1작업] ① I열 함수 결과값","point":20},
    {"code":"1C","name":"[제1작업] ② J열 함수 결과값","point":20},
    {"code":"1D","name":"[제1작업] ③ E13 함수 (집계+단위)","point":20},
    {"code":"1E","name":"[제1작업] ④ E14 함수 (통계)","point":20},
    {"code":"1F","name":"[제1작업] ⑤ J13 함수 결과값","point":20},
    {"code":"1G","name":"[제1작업] ⑥ J14 VLOOKUP 결과값","point":20},
    {"code":"1H","name":"[제1작업] ⑦ 조건부 서식","point":20},
    {"code":"1I","name":"[제1작업] 셀 서식 (단위 표시)","point":20},
    {"code":"1J","name":"[제1작업] 데이터 유효성 검사","point":20},
    {"code":"1K","name":"[제1작업] 이름 정의","point":20},
    {"code":"1L","name":"[제1작업] 채우기 색상 (주황)","point":20},
    {"code":"2A","name":"[제2작업] 목표값 찾기 / 집계 함수 결과","point":40},
    {"code":"2B","name":"[제2작업] 고급 필터 결과","point":40},
    {"code":"3A","name":"[제3작업] 부분합/피벗 구조 (정렬/그룹)","point":40},
    {"code":"3B","name":"[제3작업] 부분합/피벗 값 정확성","point":40},
    {"code":"4A","name":"[제4작업] 차트 시트 (제4작업 이름)","point":50},
    {"code":"4B","name":"[제4작업] 차트 계열 구성 (막대+꺾은선)","point":50},
]
PASS_SCORE=200
NS_SS='http://schemas.openxmlformats.org/spreadsheetml/2006/main'

def _ref_to_rc(ref):
    m=re.match(r'^([A-Za-z]+)(\d+)$',ref)
    if not m:return -1,-1
    col=0
    for ch in m.group(1).upper():col=col*26+(ord(ch)-ord('A')+1)
    return int(m.group(2))-1,col-1

def _parse_ss(zf,names):
    ss=[]
    if 'xl/sharedStrings.xml' not in names:return ss
    try:
        root=ET.fromstring(zf.read('xl/sharedStrings.xml').decode('utf-8','ignore'))
        for si in root.findall(f'{{{NS_SS}}}si'):
            t=si.find(f'{{{NS_SS}}}t')
            if t is not None:ss.append(t.text or '')
            else:
                parts=[r.find(f'{{{NS_SS}}}t') for r in si.findall(f'{{{NS_SS}}}r')]
                ss.append(''.join((p.text or '') for p in parts if p is not None))
    except:pass
    return ss

def _parse_sheet(zf,path,ss,data_only):
    cells={};sf={}
    try:raw=zf.read(path).decode('utf-8','ignore')
    except:return cells,'','',''
    try:root=ET.fromstring(raw)
    except:return cells,raw,[],[]
    cfs=[];dvs=[]
    for row_el in root.iter(f'{{{NS_SS}}}row'):
        for c_el in row_el.iter(f'{{{NS_SS}}}c'):
            ref=c_el.get('r','');ctype=c_el.get('t','')
            v_el=c_el.find(f'{{{NS_SS}}}v');f_el=c_el.find(f'{{{NS_SS}}}f')
            raw_v=(v_el.text or '') if v_el is not None else ''
            r,c=_ref_to_rc(ref)
            if r<0:continue
            formula=''
            if f_el is not None:
                ft=f_el.get('t','');si_v=f_el.get('si','');ft2=f_el.text or ''
                if ft=='shared':
                    if ft2:sf[si_v]=ft2;formula=ft2
                    else:formula=sf.get(si_v,'')
                else:formula=ft2
            if data_only:
                val=raw_v
                if ctype=='s' and raw_v.isdigit():
                    idx=int(raw_v);val=ss[idx] if idx<len(ss) else raw_v
            else:
                if formula:val='='+formula
                else:
                    val=raw_v
                    if ctype=='s' and raw_v.isdigit():
                        idx=int(raw_v);val=ss[idx] if idx<len(ss) else raw_v
            cells[(r,c)]={'v':val,'f':formula}
    for cf_el in root.iter(f'{{{NS_SS}}}conditionalFormatting'):
        sqref=cf_el.get('sqref','')
        for rule in cf_el.iter(f'{{{NS_SS}}}cfRule'):
            f2=rule.find(f'{{{NS_SS}}}formula')
            cfs.append({'sqref':sqref,'type':rule.get('type',''),'formula':(f2.text or '') if f2 is not None else ''})
    for dv_el in root.iter(f'{{{NS_SS}}}dataValidation'):
        f1=dv_el.find(f'{{{NS_SS}}}formula1')
        dvs.append({'sqref':dv_el.get('sqref',''),'type':dv_el.get('type',''),'formula1':(f1.text or '') if f1 is not None else ''})
    return cells,raw,cfs,dvs

def parse_xlsx(path,data_only=True):
    d={'sheets':{},'sheet_names':[],'shared_strings':[],'defined_names':{},'styles_xml':'',
       'conditional_fmt':{},'data_validation':{},'xml_raw':{},'chart_sheets':[],'has_chart':False,'chart_xml_list':[]}
    if not os.path.exists(path):return{'error':f'파일 없음: {path}'}
    try:zf=zipfile.ZipFile(path,'r')
    except Exception as e:return{'error':f'파일 열기 실패: {e}'}
    names=zf.namelist()
    d['shared_strings']=_parse_ss(zf,names)
    if 'xl/styles.xml' in names:
        d['styles_xml']=unescape(zf.read('xl/styles.xml').decode('utf-8','ignore'))
    if 'xl/workbook.xml' in names:
        try:
            wb=ET.fromstring(zf.read('xl/workbook.xml').decode('utf-8','ignore'))
            for sh in wb.findall(f'.//{{{NS_SS}}}sheet'):d['sheet_names'].append(sh.get('name',''))
            for dn in wb.iter(f'{{{NS_SS}}}definedName'):d['defined_names'][dn.get('name','')]=(dn.text or '')
        except:pass
    for n in names:
        if re.match(r'xl/chartsheets/sheet\d+\.xml',n):d['has_chart']=True
    if 'xl/_rels/workbook.xml.rels' in names:
        try:
            rels=zf.read('xl/_rels/workbook.xml.rels').decode('utf-8','ignore')
            if 'chartsheet' in rels:d['has_chart']=True
        except:pass
    for i in range(1,20):
        sp=f'xl/worksheets/sheet{i}.xml'
        if sp not in names:break
        sn=d['sheet_names'][i-1] if i-1<len(d['sheet_names']) else f'Sheet{i}'
        cells,raw,cfs,dvs=_parse_sheet(zf,sp,d['shared_strings'],data_only)
        d['sheets'][sn]=cells;d['xml_raw'][sn]=raw
        d['conditional_fmt'][sn]=cfs;d['data_validation'][sn]=dvs
        if '<drawing' in raw:d['has_chart']=True
    for n in names:
        if re.match(r'xl/charts/chart\d+\.xml',n):
            d['has_chart']=True
            try:d['chart_xml_list'].append(unescape(zf.read(n).decode('utf-8','ignore')))
            except:pass
    ws_count=sum(1 for n in names if re.match(r'xl/worksheets/sheet\d+\.xml',n))
    cs_count=sum(1 for n in names if re.match(r'xl/chartsheets/sheet\d+\.xml',n))
    if cs_count>0:
        for idx in range(ws_count,ws_count+cs_count):
            if idx<len(d['sheet_names']):d['chart_sheets'].append(d['sheet_names'][idx])
    zf.close()
    return d

def cv(d,s,r,c):return str(d['sheets'].get(s,{}).get((r-1,c-1),{}).get('v',''))
def fv(d,s,r,c):return str(d['sheets'].get(s,{}).get((r-1,c-1),{}).get('f',''))
def veq(a,c,tol=0.01):
    a,c=str(a).strip(),str(c).strip()
    if a==c:return True
    try:return abs(float(a)-float(c))<=tol
    except:return a.lower()==c.lower()
def _find_result_rows(d,sheet,start_row=17,max_row=30):
    result_rows=[]
    for r in range(start_row,max_row+1):
        row_vals=[cv(d,sheet,r,c) for c in range(2,9)]
        if any(v for v in row_vals):result_rows.append(r)
        elif result_rows:break
    return result_rows

def g1A(a,c,af,cf):
    s='제1작업'
    if s not in a['sheets']:return 0.0
    m=t=0
    for r in range(5,13):
        for col in range(2,7):
            va,vc=cv(a,s,r,col),cv(c,s,r,col)
            if vc:t+=1;m+=veq(va,vc)
    return m/t if t else 0.0

def g1B(a,c,af,cf):
    s='제1작업';m=t=0
    for r in range(5,13):
        va,vc=cv(a,s,r,9),cv(c,s,r,9)
        if vc:t+=1;m+=veq(va,vc)
    vr=m/t if t else 0.0
    ref_f=fv(cf,s,5,9).upper()
    kws=[kw for kw in ['RANK','IF','CHOOSE','LEFT','RIGHT','MID','YEAR','MONTH','ROUND','SUMIF','AND'] if kw in ref_f]
    fo=sum(1 for r in range(5,13) if any(kw in fv(af,s,r,9).upper() for kw in kws))
    return vr*0.7+(1.0 if fo>=1 else 0.0)*0.3

def g1C(a,c,af,cf):
    s='제1작업';m=t=0
    for r in range(5,13):
        va,vc=cv(a,s,r,10),cv(c,s,r,10)
        if vc:t+=1;m+=veq(va,vc)
    vr=m/t if t else 0.0
    ref_f=fv(cf,s,5,10).upper()
    kws=[kw for kw in ['IF','CHOOSE','LEFT','RIGHT','MID','YEAR','RANK'] if kw in ref_f]
    fo=sum(1 for r in range(5,13) if any(kw in fv(af,s,r,10).upper() for kw in kws))
    return vr*0.7+(1.0 if fo>=1 else 0.0)*0.3

def g1D(a,c,af,cf):
    s='제1작업'
    va,vc=cv(a,s,13,5),cv(c,s,13,5)
    if veq(va,vc):return 1.0
    has_unit=any(u in va for u in ['개','명','원','위','㎡','상품','점','년','층','제품']) if va else False
    ref_f=fv(cf,s,13,5).upper();ans_f=fv(af,s,13,5).upper()
    kws=[kw for kw in ['SUMIF','COUNTIF','DSUM','DAVERAGE','LARGE','SMALL','RANK','ROUND','MIN','MAX'] if kw in ref_f]
    fok=any(kw in ans_f for kw in kws) if kws else False
    return (0.4 if has_unit else 0)+(0.3 if fok else 0)

def g1E(a,c,af,cf):
    s='제1작업'
    va,vc=cv(a,s,14,5),cv(c,s,14,5)
    try:
        if abs(float(va)-float(vc))<=abs(float(vc))*0.01:return 1.0
    except:pass
    if veq(va,vc):return 1.0
    ref_f=fv(cf,s,14,5).upper();ans_f=fv(af,s,14,5).upper()
    kws=[kw for kw in ['ROUND','ROUNDUP','ROUNDDOWN','DAVERAGE','DSUM','SUMIF','COUNTIF','LARGE','SMALL'] if kw in ref_f]
    fok=any(kw in ans_f for kw in kws) if kws else False
    return 0.3 if fok else 0.0

def g1F(a,c,af,cf):
    s='제1작업'
    va,vc=cv(a,s,13,10),cv(c,s,13,10)
    if veq(va,vc):return 1.0
    try:
        av=re.sub(r'[^0-9.\-]','',va);cv2=re.sub(r'[^0-9.\-]','',vc)
        if av and cv2 and abs(float(av)-float(cv2))<=abs(float(cv2))*0.01:return 1.0
    except:pass
    ref_f=fv(cf,s,13,10).upper();ans_f=fv(af,s,13,10).upper()
    kws=[kw for kw in ['MAX','MIN','LARGE','SMALL','SUMPRODUCT','COUNTIF','SUMIF'] if kw in ref_f]
    fok=any(kw in ans_f for kw in kws) if kws else False
    return 0.3 if fok else 0.0

def g1G(a,c,af,cf):
    s='제1작업'
    va,vc=cv(a,s,14,10),cv(c,s,14,10)
    if veq(va,vc):return 1.0
    try:
        av=re.sub(r'[^0-9.\-]','',va);cv2=re.sub(r'[^0-9.\-]','',vc)
        if av and cv2 and abs(float(av)-float(cv2))<=abs(float(cv2))*0.01:return 1.0
    except:pass
    return 0.3 if 'VLOOKUP' in fv(af,s,14,10).upper() else 0.0

def g1H(a,c,af,cf):
    s='제1작업'
    cor_cfs=c['conditional_fmt'].get(s,[])
    ans_cfs=a['conditional_fmt'].get(s,[])
    if not cor_cfs:return 0.5
    if not ans_cfs:return 0.0
    cor_types=[cf2.get('type','') for cf2 in cor_cfs]
    if 'dataBar' in cor_types:
        return 1.0 if 'dataBar' in [cf2.get('type','') for cf2 in ans_cfs] else 0.0
    ref_formula=cor_cfs[0].get('formula','').upper()
    numbers=re.findall(r'\d+',ref_formula)
    for cf2 in ans_cfs:
        f=cf2.get('formula','').upper()
        if not f:continue
        if numbers and any(n in f for n in numbers):
            if any(op in f for op in ['>=','<=','>','<','=']):return 1.0
        if f==ref_formula:return 1.0
    return 0.0

def g1I(a,c,af,cf):
    sa=a.get('styles_xml','');sc=c.get('styles_xml','')
    units=['개','명','원','위','㎡','상품','점','년','층']
    cor_unit=None
    for u in units:
        if u in sc and (f'"{u}"' in sc or f'{u}"' in sc or f'0{u}' in sc):
            cor_unit=u;break
    if cor_unit:
        ans_has=cor_unit in sa and (f'"{cor_unit}"' in sa or f'{cor_unit}"' in sa or f'0{cor_unit}' in sa)
        return 1.0 if ans_has else 0.0
    s='제1작업';header_units=[]
    if s in c['sheets']:
        for col in range(2,11):
            hv=str(c['sheets'].get(s,{}).get((3,col-1),{}).get('v',''))
            for u in units:
                if u in hv and u not in header_units:header_units.append(u)
    if not header_units:return 0.5
    for u in header_units:
        if u in sa and (f'"{u}"' in sa or f'{u}"' in sa or f'0{u}' in sa):return 1.0
    return 0.5

def g1J(a,c,af,cf):
    s='제1작업'
    cor_dvs=c['data_validation'].get(s,[])
    ans_dvs=a['data_validation'].get(s,[])
    if not cor_dvs:return 0.5
    if not ans_dvs:return 0.0
    for dv in ans_dvs:
        sq=dv.get('sqref','').upper();tp=dv.get('type','').lower();f1=dv.get('formula1','')
        if 'H14' in sq and tp=='list':return 1.0
        if tp=='list' and f1:return 0.7
    return 0.4 if ans_dvs else 0.0

def g1K(a,c,af,cf):
    cor_dn=c.get('defined_names',{});ans_dn=a.get('defined_names',{})
    cor_real=[k for k in cor_dn if not k.startswith('_')]
    ans_real=[k for k in ans_dn if not k.startswith('_')]
    if cor_real:
        for cn in cor_real:
            if cn in ans_dn:return 1.0
        return 0.0 if not ans_real else 0.3
    if ans_real:return 1.0
    return 0.5

def g1L(a,c,af,cf):
    sa=a.get('styles_xml','');sc=c.get('styles_xml','')
    oranges=['FF7F00','FFA500','FF6600','FF8C00','FFC000','E26B0A','F79646','FF9900','FFC300','FFB050','F4B942','FABF8F','E36C09']
    cor_has=any(rgb.upper() in sc.upper() for rgb in oranges) or 'indexed="46"' in sc or 'indexed="53"' in sc
    if not cor_has:return 0.5
    return 1.0 if (any(rgb.upper() in sa.upper() for rgb in oranges) or 'indexed="46"' in sa or 'indexed="53"' in sa) else 0.0

def g2A(a,c,af,cf):
    s='제2작업'
    if s not in a['sheets']:return 0.0
    for col in range(6,10):
        va=cv(a,s,11,col);vc=cv(c,s,11,col)
        if vc:
            try:
                if abs(float(va)-float(vc))<=abs(float(vc))*0.02:return 1.0
            except:pass
            if veq(va,vc,0.5):return 1.0
    for r in range(7,12):
        for col in range(2,10):
            va=cv(a,s,r,col);vc=cv(c,s,r,col)
            if vc and vc not in ['','**','***']:
                try:
                    if abs(float(va)-float(vc))<=abs(float(vc))*0.05:return 0.8
                except:pass
    return 0.0

def g2B(a,c,af,cf):
    s='제2작업'
    if s not in a['sheets']:return 0.0
    cor_rows=_find_result_rows(c,s,18,30)
    ans_rows=_find_result_rows(a,s,18,30)
    if not cor_rows:return 0.5
    if not ans_rows:return 0.0
    m=t=0
    for r in cor_rows:
        for col in range(2,9):
            vc=cv(c,s,r,col)
            if vc:t+=1;va=cv(a,s,r,col);m+=veq(va,vc)
    return m/t if t else 0.0

def g3A(a,c,af,cf):
    s='제3작업'
    if s not in a['sheets']:return 0.0
    if s not in c['sheets']:return 0.5
    cor_rows=[r for r in range(2,25) if any(cv(c,s,r,col) for col in range(2,9))]
    ans_rows=[r for r in range(2,25) if any(cv(a,s,r,col) for col in range(2,9))]
    if not cor_rows:return 0.5
    row_ratio=min(len(ans_rows),len(cor_rows))/len(cor_rows)
    keywords=['평균','개수','합계','최대','최소','요약','총합']
    cor_kw=sum(1 for r in cor_rows for col in range(2,9) if any(k in cv(c,s,r,col) for k in keywords))
    ans_kw=sum(1 for r in ans_rows for col in range(2,9) if any(k in cv(a,s,r,col) for k in keywords))
    kw_ratio=min(ans_kw,cor_kw)/(cor_kw+1)
    return row_ratio*0.5+kw_ratio*0.5

def g3B(a,c,af,cf):
    s='제3작업'
    if s not in a['sheets']:return 0.0
    if s not in c['sheets']:return 0.5
    m=t=0
    for r in range(2,25):
        for col in range(2,9):
            vc=cv(c,s,r,col)
            if not vc or vc in ['**','***']:continue
            vc_clean=re.sub(r'[^0-9.\-]','',vc)
            if not vc_clean:continue
            try:float(vc_clean)
            except:continue
            t+=1;va=cv(a,s,r,col);va_clean=re.sub(r'[^0-9.\-]','',va)
            try:
                if abs(float(va_clean)-float(vc_clean))<=abs(float(vc_clean))*0.02:m+=1
            except:pass
    return m/t if t>0 else 0.0

def g4A(a,c,af,cf):
    for n in a.get('chart_sheets',[]):
        if '제4작업' in n or '4작업' in n:return 1.0
    for n in a.get('sheet_names',[]):
        if '제4작업' in n or '4작업' in n:
            return 1.0 if a['has_chart'] else 0.8
    if a.get('chart_sheets'):return 0.7
    return 0.3 if a['has_chart'] else 0.0

def g4B(a,c,af,cf):
    if not a['has_chart']:return 0.0
    chart_xmls=a.get('chart_xml_list',[])
    if not chart_xmls:
        for xml in a.get('xml_raw',{}).values():
            if 'barChart' in xml or 'lineChart' in xml:chart_xmls.append(xml)
    has_bar=any('barChart' in x for x in chart_xmls)
    has_line=any('lineChart' in x for x in chart_xmls)
    cor_xmls=c.get('chart_xml_list',[])
    if not cor_xmls:
        return 0.8 if a.get('chart_sheets') else (0.4 if a['has_chart'] else 0.0)
    if has_bar and has_line:return 1.0
    if has_bar or has_line:return 0.6
    return 0.4 if a['has_chart'] else 0.0

GRADE_FUNCS={'1A':g1A,'1B':g1B,'1C':g1C,'1D':g1D,'1E':g1E,'1F':g1F,'1G':g1G,
             '1H':g1H,'1I':g1I,'1J':g1J,'1K':g1K,'1L':g1L,
             '2A':g2A,'2B':g2B,'3A':g3A,'3B':g3B,'4A':g4A,'4B':g4B}

def grade_excel(ap,cp):
    a=parse_xlsx(ap,True);c=parse_xlsx(cp,True)
    af=parse_xlsx(ap,False);cf=parse_xlsx(cp,False)
    if 'error' in a:return{'error':a['error']}
    if 'error' in c:return{'error':c['error']}
    results=[];total=0
    for item in ITEMS:
        fn=GRADE_FUNCS.get(item['code'])
        try:ratio=max(0.0,min(1.0,fn(a,c,af,cf) if fn else 0.5))
        except:ratio=0.0
        if ratio>=0.8:earned,ok=item['point'],True
        elif ratio>=0.5:earned,ok=item['point']//2,False
        else:earned,ok=0,False
        total+=earned
        results.append({'code':item['code'],'name':item['name'],'point':item['point'],'earned':earned,'ok':ok,'ratio':round(ratio,3)})
    return{'subject':'excel','total':500,'score':total,'pass':total>=PASS_SCORE,'pass_score':PASS_SCORE,'items':results}

if __name__=='__main__':
    if len(sys.argv)<3:
        print(json.dumps({'error':'Usage: excel_grader.py <answer.xlsx> <correct.xlsx>'}));sys.exit(1)
    print(json.dumps(grade_excel(sys.argv[1],sys.argv[2]),ensure_ascii=False))