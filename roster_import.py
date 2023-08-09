#!/usr/bin/python3
import json
from pathlib import Path
import pandas

def read_with_callback(input_file, output_function):
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
            role=item['Role'],
            sections=item['Sections'],
            email=item['Email']
        )

def csv_for_ohq(input_file):
    csv_out = 'name,id,email,role\n'
    def _add(name, id, role, sections, email):
        nonlocal csv_out
        csv_out += '{},{},{},{}\n'.format(name, id, email, role)
    read_with_callback(input_file, _add)
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

def archimedes_import(input_file, directory):
    found = set()
    def _add(name, id, role, sections, email):
        nonlocal found
        found.add(id)
        with open(directory / 'users' / (id + '.json'), 'w') as fh:
            json.dump({
                'name': name,
                'id': id,
                'role': role,
                'sections': sections,
                'email': email,
            }, fp=fh)
    read_with_callback(input_file, _add)
    has_json = set()
    for item in (directory / 'users').iterdir():
        if item.suffix == 'json':
            has_json.add(item)

    for name in has_json - found:
        print("extra user", name)


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('--ohq-dir', default='/opt/ohq-cr4bd/logs')
    parser.add_argument('--ohq-host', default='https://kytos02.cs.virginia.edu:1112')
    parser.add_argument('--course', default='cs3130')
    parser.add_argument('--archimedes-dir')
    parser.add_argument('--mode', default='upload')
    parser.add_argument('input_file')
    args = parser.parse_args()
    if args.mode == 'archimedes':
        archimedes_import(args.input_file, Path(args.archimedes_dir))
    elif args.mode == 'upload':
        csv = csv_for_ohq(args.input_file)
        upload_csv(Path(args.ohq_dir), args.ohq_host, args.course, csv)
    else:
        print("unknown mode")
