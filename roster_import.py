#!/usr/bin/python3
import csv
import json
from pathlib import Path
import pandas

def canvas_read_with_callback(input_file, output_function):
    df = pandas.read_excel(
        input_file,
        sheet_name=1,
        header=1,
    )
    for i, item in df.iterrows():
        if isinstance(item['First Name'], float):
            continue
        output_function(
            name='{} {}'.format(item['First Name'].strip(), item['Last Name']),
            id=item['Login ID'],
            role=item['Role(s)'],
            sections=item['Sections'],
            email=item['Email']
        )

def sis_read_with_callback(input_file, output_function):
    with open(input_file, 'r') as fh:
        reader = csv.DictReader(fh)
        for row in reader:
            name_parts = row['Name'].split(', ')
            login_id = None
            status = row['Status']
            role = None
            if status == 'Enrolled':
                role = 'Student'
            elif status == 'Waiting':
                role = 'Waitlisted Student'
            else:
                print("Unknown role", role)
            if row['Email Address'].endswith('@virginia.edu'):
                login_id = row['Email Address'].split('@')[0]
            if login_id and role:
                output_function(
                    name=name_parts[1] + ' ' + name_parts[0],
                    id=login_id,
                    sis_id=row['Student ID'],
                    role=role,
                    email=row['Email Address'],
                )
            else:
                print("No login ID for", row)

def csv_for_ohq(read_with_callback):
    csv_out = 'name,id,email,role\n'
    def _add(name, id, role, email, **extra):
        nonlocal csv_out
        csv_out += '{},{},{},{}\n'.format(name, id, email, role)
    read_with_callback(_add)
    return csv_out

def upload_csv(directory, host, course, csv):
    import datetime
    import pytz
    import secrets
    import requests
    token = '{} {}'.format(
            secrets.token_hex(8),
            datetime.datetime.now(pytz.utc).isoformat()
        )
    with open(directory / 'sessions/uploadscript', 'w') as fh:
        fh.write(token)
    r = requests.post(
        host + '/uploadroster',
        data = {
            'user': 'uploadscript',
            'course': course,
            'token': token,
        },
        files={
            'file': (
                'roster.csv', csv.encode('UTF-8'), 'text/csv'
            ),
        },
    )
    print(r.text)

def archimedes_import(read_with_callback, directory):
    found = set()
    def _add(name, id, role, email, sections=None, **extra):
        nonlocal found
        found.add(id)
        if isinstance(id, float):
            return
        file = directory / 'users' / (id + '.json')
        if file.exists():
            data = json.load(file.open())
        else:
            data = {}
        data.update({
            'name': name,
            'id': id,
            'role': role,
            'email': email,
        })
        if sections:
            data['sections'] = sections
        with open(directory / 'users' / (id + '.json'), 'w') as fh:
            json.dump(data, fp=fh)
    read_with_callback(_add)
    has_json = set()
    for item in (directory / 'users').iterdir():
        if item.suffix == '.json':
            has_json.add(item)
    for name in has_json - found:
        print("extra user", name)
    roster = {}
    for item in (directory / 'users').iterdir():
        print(item)
        if item.suffix == '.json':
            roster[item.stem] = json.loads(item.read_text())
    with open(directory / 'meta' / 'roster.json', 'w') as fh:
        json.dump(roster, fp=fh, indent=2)



if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('--ohq-dir', default='/opt/ohq-cr4bd/logs')
    parser.add_argument('--ohq-host', default='https://kytos02.cs.virginia.edu:1112')
    parser.add_argument('--course')
    parser.add_argument('--archimedes-dir')
    parser.add_argument('--mode', default='upload')
    parser.add_argument('--canvas', default=None)
    parser.add_argument('--sis', default=None)
    args = parser.parse_args()
    if args.canvas:
        read_with_callback = lambda callback: canvas_read_with_callback(args.canvas, callback)
    elif args.sis:
        read_with_callback = lambda callback: sis_read_with_callback(args.sis, callback)
    if args.mode == 'archimedes':
        archimedes_import(read_with_callback, Path(args.archimedes_dir))
    elif args.mode == 'upload':
        csv = csv_for_ohq(read_with_callback)
        upload_csv(Path(args.ohq_dir), args.ohq_host, args.course, csv)
    else:
        print("unknown mode")
