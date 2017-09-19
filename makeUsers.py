import csv, json
students = {}
tas = {}
with open('/var/www/html/cs1110/uploads/roster.csv', 'r') as f:
    r = csv.reader(f)
    for row in r:
        if len(row) < 3: continue
        if len(row[0]) == 0 or len(row[1]) == 0: continue
        if row[1] in students: del students[row[1]]
        if row[1] in tas: del tas[row[1]]
        if 'tudent' in row[2]:
            students[row[1].strip().lower()] = ' '.join([_.strip() for _ in reversed(row[0].split(','))])
        elif 'each' in row[2] or 'rofess' in row[2] or 'nstruct' in row[2]:
            tas[row[1].strip().lower()] = ' '.join([_.strip() for _ in reversed(row[0].split(','))])

for k,v in tas.items():
    print('{"action":"ta","id":"'+k+'","name":"'+v+'"}')
for k,v in students.items():
    print('{"action":"student","id":"'+k+'","name":"'+v+'"}')
