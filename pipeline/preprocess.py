import argparse
import pandas as pd

parser = argparse.ArgumentParser()
parser.add_argument('input', help='Input CSV file path')
parser.add_argument('output', help='Output CSV file path')
args = parser.parse_args()

df = pd.read_csv(args.input)
df.info()

df.drop(inplace=True, columns=['vote_count', 'adult', 'budget', 'original_title', 'production_countries', \
        'spoken_languages'])
df.rename(inplace=True, columns={'vote_average': 'rating', 'backdrop_path': 'img_backdrop', 'original_language': 'language', \
          'poster_path': 'img_poster'})
df = df.dropna(subset=['title', 'img_backdrop', 'overview', 'img_poster'])

import pycountry

codes = df['language'].unique()

def get_language_name(code):
    try:
        lang = pycountry.languages.get(alpha_2=code)
        return lang.name
    except:
        return "Unknown"

df_lang = pd.DataFrame({
    "Code": codes,
    "Language": [get_language_name(code) for code in codes]
})
df_lang['id'] = range(1, len(df_lang)+1)

df_lang.loc[len(df_lang)] = ['Unknown', 'Unknown', len(df_lang)+1]

df['lang_code_clean'] = df['language'].fillna('Unknown').replace('', 'Unknown')

lang_id_map = df_lang.set_index('Code')['id'].to_dict()


df['language_id'] = df['lang_code_clean'].apply(lambda x: lang_id_map.get(x, lang_id_map['Unknown']))

df.drop(columns=['lang_code_clean', 'language'], inplace=True)

df.to_csv(args.output, index=False, sep='|')
