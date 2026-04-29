#!/usr/bin/env python3
"""ITQ PPT 채점 엔진"""
import sys, json, os, zipfile, re

ITEMS = [
    {"code":"1A","name":"[제1작업] 슬라이드 수 및 레이아웃","point":20},
    {"code":"1B","name":"[제1작업] 텍스트 상자 서식","point":20},
    {"code":"1C","name":"[제1작업] 그림 삽입","point":20},
    {"code":"1D","name":"[제1작업] 도형 삽입 및 서식","point":20},
    {"code":"1E","name":"[제1작업] SmartArt","point":20},
    {"code":"1F","name":"[제1작업] 표 삽입","point":20},
    {"code":"1G","name":"[제1작업] 차트 삽입","point":20},
    {"code":"1H","name":"[제1작업] 애니메이션","point":20},
    {"code":"1I","name":"[제1작업] 슬라이드 전환","point":20},
    {"code":"1J","name":"[제1작업] 마스터/배경","point":20},
    {"code":"2A","name":"[제2작업] 도형/텍스트 서식","point":60},
    {"code":"3A","name":"[제3작업] 도형/그림/차트","point":60},
    {"code":"4A","name":"[제4작업] 차트 시트","point":60},
    {"code":"5A","name":"[제5작업] 슬라이드 쇼 설정","point":60},
]

def parse_pptx(path):
    d = {'slide_count':0,'slides_xml':[],'has_chart':False,'has_table':False,
         'has_picture':False,'has_smartart':False,'has_anim':False,'has_trans':False,
         'has_hyperlink':False,'has_group':False,'shape_count':0,'text_runs':0,'master_count':0}
    try:
        zf = zipfile.ZipFile(path, 'r')
        names = zf.namelist()
        for i in range(1, 30):
            sp = f'ppt/slides/slide{i}.xml'
            if sp not in names: break
            try:
                x = zf.read(sp).decode('utf-8', 'ignore')
                d['slide_count'] += 1; d['slides_xml'].append(x)
                if '<p:graphicFrame' in x or 'c:chart' in x: d['has_chart'] = True
                if '<a:tbl' in x: d['has_table'] = True
                if '<p:pic' in x: d['has_picture'] = True
                if 'dgm:' in x: d['has_smartart'] = True
                if 'p:timing' in x: d['has_anim'] = True
                if 'p:transition' in x: d['has_trans'] = True
                if 'hlinkClick' in x: d['has_hyperlink'] = True
                if '<p:grpSp' in x: d['has_group'] = True
                d['shape_count'] += x.count('<p:sp>') + x.count('<p:sp ')
                d['text_runs'] += x.count('<a:r>')
            except: pass
        for i in range(1, 10):
            if f'ppt/slideMasters/slideMaster{i}.xml' in names: d['master_count'] += 1
            else: break
        if any(re.match(r'ppt/charts/chart\d+\.xml', n) for n in names): d['has_chart'] = True
        if any('diagrams' in n for n in names): d['has_smartart'] = True
        zf.close()
    except Exception as e:
        d['error'] = str(e)
    return d

def grade_ppt(ap, cp):
    if not os.path.exists(ap): return {'error': f'파일 없음: {ap}'}
    if not os.path.exists(cp): return {'error': f'정답 파일 없음: {cp}'}
    a = parse_pptx(ap); c = parse_pptx(cp)
    if 'error' in a: return {'error': a['error']}
    if 'error' in c: return {'error': c['error']}

    def cb(av, cv): return 1.0 if not cv else (1.0 if av else 0.0)
    def cr(av, cv): return 0.5 if cv==0 else min(av/cv, 1.0)

    g = {
        '1A': cr(a['slide_count'], c['slide_count']),
        '1B': cr(a['text_runs'], max(c['text_runs'],1)),
        '1C': cb(a['has_picture'], c['has_picture']),
        '1D': cr(a['shape_count'], max(c['shape_count'],1)),
        '1E': cb(a['has_smartart'], c['has_smartart']),
        '1F': cb(a['has_table'], c['has_table']),
        '1G': cb(a['has_chart'], c['has_chart']),
        '1H': cb(a['has_anim'], c['has_anim']),
        '1I': cb(a['has_trans'], c['has_trans']),
        '1J': cr(a['master_count'], max(c['master_count'],1)),
        '2A': 0.5, '3A': 0.5, '4A': 0.5, '5A': 0.5,
    }

    results=[]; total=0
    for item in ITEMS:
        ratio=max(0.0,min(1.0,g.get(item['code'],0.5)))
        if ratio>=0.8: earned,ok=item['point'],True
        elif ratio>=0.5: earned,ok=item['point']//2,False
        else: earned,ok=0,False
        total+=earned
        results.append({'code':item['code'],'name':item['name'],'point':item['point'],
                        'earned':earned,'ok':ok,'ratio':round(ratio,3)})
    return {'subject':'ppt','total':500,'score':total,'pass':total>=200,'pass_score':200,'items':results}

if __name__=='__main__':
    if len(sys.argv)<3:
        print(json.dumps({'error':'Usage: ppt_grader.py <answer.pptx> <correct.pptx>'})); sys.exit(1)
    print(json.dumps(grade_ppt(sys.argv[1],sys.argv[2]),ensure_ascii=False))