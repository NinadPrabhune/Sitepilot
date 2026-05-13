import re

with open(r'C:\Users\ninad\Desktop\contacts.txt', 'r') as f:
    lines = f.readlines()

emails = []
for i in range(1, len(lines), 2):
    line = lines[i].strip()
    if '@' in line and '.' in line:
        emails.append(line)

print(','.join(emails))