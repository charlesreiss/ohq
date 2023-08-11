#!/usr/bin/python3
# Extract photos from photos.html in HTML dump of UVACanvas Photo Roster to photos/*.jpg
from bs4 import BeautifulSoup

def extract_images(soup):
    for person_div in soup.find_all(attrs={'class':'single_person'}):
        img = person_div.find('img').attrs['src'];
        login_id_label = person_div.find(string='Login ID').parent
        login_id_div = login_id_label.find_next_sibling('div')
        yield (login_id_div.string, img)

def url_to_image(img):
    import base64
    if not img.startswith('data:image/jpg;base64,'):
        raise Exception("unsupported image URL")
    base64_data = img[len('data:image/jpg;base64,'):]
    return base64.b64decode(base64_data)

if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('input', type=argparse.FileType('r'))
    args = parser.parse_args()
    for compid, image_url in extract_images(BeautifulSoup(args.input, 'html.parser')):
        with open('photos/{}.jpg'.format(compid), 'wb') as fh:
            fh.write(url_to_image(image_url))


