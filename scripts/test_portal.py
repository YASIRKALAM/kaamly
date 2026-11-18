import subprocess
import urllib.request
import time

p = subprocess.Popen([r"d:\Study\code\.venv\Scripts\python.exe", r"d:\Study\code\Kaamly\scripts\run_portal.py"])
time.sleep(2)
try:
    with urllib.request.urlopen("http://127.0.0.1:3000") as resp:
        print("PORTAL HTTP RESPONSE STATUS:", resp.status)
        assert resp.status == 200
        print("SUCCESS: Kaamly Web Portal is working and responding perfectly!")
finally:
    p.terminate()
