#!/usr/bin/env python3
"""ITQ 한글 채점 엔진"""
import sys, json, os, zipfile

ITEMS = [
    {"code":"1A","name":"[제1작업] 스타일 작성 및 적용","point":40},
    {"code":"1B","name":"[제1작업] 표 만들기","point":60},
    {"code":"1C","name":"[제1작업] 차트 작성","point":40},
    {"code":"1D","name":"[제1작업] 수식 편집기","point":40},
    {"code":"1E","name":"[제1작업] 그림 삽입","point":20},
    {"code":"1F","name":"[제1작업] 그리기 도구(도형/글상자)","point":40},
    {"code":"1G","name":"[제1작업] 머리말/꼬리말/쪽 번호","point":30},
    {"code":"1H","name":"[제1작업] 다단 편집","point":30},
    {"code":"1I","name":"[제1작업] 문단 서식","point":30},
    {"code":"1J","name":"[제1작업] 글자 서식","point":20},
    {"code":"1K","name":"[제1작업] 쪽 여백/용지 설정","point":20},
    {"code":"1L","name":"[제1작업] 하이퍼링크","point":20},
    {"code":"1M","name":"[제1작업] 책갈피/상호 참조","point":20},
    {"code":"1N","name":"[제1작업] 목차 자동 생성","point":30},
    {"code":"1O","name":"[제1작업] 메일 머지","point":30},
]

def read_hwpx(path):
    if not os.path.exists(path): return ''
    try:
        zf = zipfile.ZipFile(path, 'r')
        names = zf.namelist()
        all_xml = ''
        for n in names:
            if ('section' in n and n.endswith('.xml')) or \
               n.endswith('content.hpf') or n.endswith('header.xml'):
                try: all_xml += zf.read(n).decode('utf-8', 'ignore')
                except: pass
        if not all_xml:
            for n in names:
                if n.endswith('.xml'):
                    try: all_xml += zf.read(n).decode('utf-8', 'ignore')
                    except: pass
        zf.close()
        return all_xml
    except: return ''

def cnt(xml, *tags):
    n = 0
    for t in tags:
        n += xml.count(f'<{t}') + xml.count(f'<hp:{t}') + xml.count(f'<hh:{t}')
    return n

def ct(a_xml, c_xml, *tags):
    ac = cnt(a_xml, *tags); cc = cnt(c_xml, *tags)
    if cc == 0: return 0.5
    if ac == 0: return 0.0
    r = ac / cc
    return 1.0 if r >= 0.7 else (0.5 if r >= 0.4 else 0.2)

def grade_hangul(ap, cp):
    a_xml = read_hwpx(ap); c_xml = read_hwpx(cp)
    if not a_xml: return {'error': '수험자 파일을 읽을 수 없습니다. .hwpx 형식인지 확인하세요.'}
    if not c_xml: return {'error': '정답 파일을 읽을 수 없습니다.'}

    g = {
        '1A': ct(a_xml,c_xml,'style','charShape'),
        '1B': ct(a_xml,c_xml,'tbl','tc'),
        '1C': ct(a_xml,c_xml,'chart','oc:chart'),
        '1D': ct(a_xml,c_xml,'equation','math'),
        '1E': ct(a_xml,c_xml,'pic','picture'),
        '1F': ct(a_xml,c_xml,'container','drawingObject','textbox'),
        '1G': ct(a_xml,c_xml,'header','footer','pageNum'),
        '1H': ct(a_xml,c_xml,'column','sectionDef'),
        '1I': ct(a_xml,c_xml,'para','p'),
        '1J': ct(a_xml,c_xml,'charShape'),
        '1K': ct(a_xml,c_xml,'pageInfo','pageDef'),
        '1L': ct(a_xml,c_xml,'hyperlink'),
        '1M': ct(a_xml,c_xml,'bookmark'),
        '1N': ct(a_xml,c_xml,'toc','fieldBegin'),
        '1O': ct(a_xml,c_xml,'mergeField','mailMerge'),
    }

    results=[]; total=0
    for item in ITEMS:
        ratio=max(0.0,min(1.0,g.get(item['code'],0.5)))
        if ratio>=0.7: earned,ok=item['point'],True
        elif ratio>=0.4: earned,ok=item['point']//2,False
        else: earned,ok=0,False
        total+=earned
        results.append({'code':item['code'],'name':item['name'],'point':item['point'],
                        'earned':earned,'ok':ok,'ratio':round(ratio,3)})
    return {'subject':'hangul','total':500,'score':total,'pass':total>=200,'pass_score':200,'items':results}

if __name__=='__main__':
    if len(sys.argv)<3:
        print(json.dumps({'error':'Usage: hangul_grader.py <answer.hwpx> <correct.hwpx>'})); sys.exit(1)
    print(json.dumps(grade_hangul(sys.argv[1],sys.argv[2]),ensure_ascii=False))