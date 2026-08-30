import yaml
import json
import urllib.request
import urllib.error
import os
import glob
import time

API_KEY = os.environ.get('GEMINI_API_KEY', 'AQ.Ab8RN6IeGxRBWvBYTWui29S5whZmeBgRGJVXCfssacaT-Ns_sA')
URL = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key={API_KEY}"

class Dumper(yaml.Dumper):
    def represent_scalar(self, tag, value, style=None):
        if isinstance(value, str) and '\n' in value:
            style = '|'
        return super().represent_scalar(tag, value, style)

def process_batch(batch):
    input_data = []
    for q in batch:
        correct_ans = ""
        for ans in q['version']['answers']:
            if ans.get('is_correct') == 1:
                correct_ans = ans['text']
                break
        input_data.append({
            "id": q['id_zew'],
            "q": q['version']['question_text'],
            "ans": correct_ans,
            "exp": q['version'].get('explanation', '')
        })

    prompt = """Jesteś doświadczonym programistą i mentorem dla uczniów technikum przygotowujących się do egzaminu INF.04. 
Przeredaguj poniższe wyjaśnienia do pytań.
Wytyczne STYLU:
1. Budź zaufanie, bądź jak doświadczony starszy kolega po fachu. Pełen profesjonalizm, ale podany przystępnym, życiowym tonem. Żadnych kiczowatych żartów.
2. Zrozumiale 'na chłopski rozum' - celnie i do brzegu. Unikaj suchego, encyklopedycznego żargonu, jednak nie zaniżaj poziomu wiedzy technicznej.
3. DŁUGOŚĆ I FORMAT: Pisz do pełnego wyczerpania zagadnienia. Uczeń musi pojąć 'dlaczego' tak jest. Nie stosuj limitu zdań, jeśli temat wymaga szerszego omówienia, jednak pamiętaj by unikać 'ściany tekstu'. Dziel tekst na krótkie, przystępne akapity. 
4. WAŻNE: Nie używaj formatowania Markdown (żadnych pogrubień, gwiazdek, backticków). Zwróć czysty tekst z zachowaniem nowych linii.

Oto tablica JSON z danymi. Zwróć WYŁĄCZNIE tablicę JSON, w której dla każdego 'id' przypiszesz nowo sformułowane wyjaśnienie pod kluczem 'new_exp'. Żadnego formatowania bloków kodu, tylko czysty kod JSON.

Dane wejściowe:
""" + json.dumps(input_data, ensure_ascii=False)

    data = {
        "contents": [{"role": "user", "parts": [{"text": prompt}]}],
        "generationConfig": {"responseMimeType": "application/json"}
    }
    
    req = urllib.request.Request(URL, data=json.dumps(data).encode('utf-8'), headers={'Content-Type': 'application/json'})
    
    retries = 3
    while retries > 0:
        try:
            with urllib.request.urlopen(req, timeout=30) as response:
                res = json.loads(response.read().decode('utf-8'))
                text = res['candidates'][0]['content']['parts'][0]['text']
                if text.startswith('```json'):
                    text = text.replace('```json', '').replace('```', '')
                return json.loads(text)
        except urllib.error.HTTPError as e:
            err_msg = e.read().decode('utf-8')
            print(f"Błąd HTTP {e.code}: {err_msg}")
            retries -= 1
            time.sleep(2)
        except Exception as e:
            print(f"Inny błąd API: {e}. Próbuje ponownie...")
            retries -= 1
            time.sleep(2)
            
    return []

files = sorted(glob.glob("zdajnik-newdb/data/export/inf04/db/*.yaml"))
total_updated = 0
for file_path in files:
    print(f"\n--- Przetwarzanie {file_path} ---")
    with open(file_path, "r", encoding="utf-8") as f:
        data = yaml.safe_load(f)
        
    questions = data.get("questions", [])
    batch = []
    
    for idx, q in enumerate(questions):
        if q['version'].get('image_name'):
            continue
        if q['version'].get('explanation'):
            batch.append(q)
            
        if len(batch) >= 15 or (idx == len(questions) - 1 and len(batch) > 0):
            print(f"Wysyłam paczkę {len(batch)} pytań...")
            results = process_batch(batch)
            if results:
                for res in results:
                    for bq in batch:
                        if bq['id_zew'] == res['id']:
                            bq['version']['explanation'] = res['new_exp'] + '\n'
                            total_updated += 1
            else:
                print("Nie udało się zaktualizować tej paczki (błąd API).")
            batch = []
                    
    with open(file_path, "w", encoding="utf-8") as f:
        yaml.dump(data, f, Dumper=Dumper, allow_unicode=True, sort_keys=False)

print(f"\nZakończono! Zaktualizowano łącznie {total_updated} wyjaśnień.")
