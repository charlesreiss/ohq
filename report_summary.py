import json

reports = {}

expander = {
 "unhurried":'took enough time',
 "hurried":'rushed',
 "listened":'listened to my questions',
 "condescended":'was condescending',
 "learning":'focused on my learning more than on solving my problem',
 "solving":'focused on solving my problem more than on my learning'
}

with open('logs/cs1110.log') as f:
    for line in f:
        d = json.loads(line)
        if d['action'] == 'report':
            ta = d['ta']
            reports.setdefault(ta, {'count':0, 'comments':[]})['count'] += 1
            for term in d['notes'].split(','):
                term = expander.get(term, term)
                reports[ta].setdefault(term, 0)
                reports[ta][term] += 1
            if d.get('comments',''): reports[ta]['comments'].append(d['comments'])

reports = {k:v for k,v in reports.items() if v['count'] > 4}

for k,v in reports.items():
    for k2 in v:
        if k2 not in ['count', 'comments']:
            v[k2] /= v['count']
            v[k2] = int(round(v[k2]*100,0 if v['count'] > 10  else -1))
    # v.pop('count')

print(json.dumps(reports))
