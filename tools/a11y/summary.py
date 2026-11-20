"""Groups the violations tools/a11y/scan.sh wrote, worst first. Usage: python3 tools/a11y/summary.py <out-dir>"""
import collections
import json
import pathlib
import sys

ORDER = {"critical": 0, "serious": 1, "moderate": 2, "minor": 3}
rules = collections.defaultdict(lambda: {"impact": "", "help": "", "screens": [], "nodes": 0, "targets": set()})

for path in sorted(pathlib.Path(sys.argv[1]).glob("*.json")):
    for violation in json.loads(json.loads(path.read_text().strip())):
        rule = rules[violation["id"]]
        rule.update(impact=violation["impact"], help=violation["help"])
        rule["screens"].append(path.stem)
        rule["nodes"] += violation["nodes"]
        rule["targets"].update(violation["targets"][:2])

print(f"{len(rules)} rules failing")
for name, rule in sorted(rules.items(), key=lambda item: (ORDER.get(item[1]["impact"], 9), -len(item[1]["screens"]))):
    print(f"{rule['impact']:9} {name:24} screens={len(rule['screens']):2} nodes={rule['nodes']:3}  {rule['help']}")
    print("          ", ", ".join(rule["screens"][:6]))
    for target in list(rule["targets"])[:3]:
        print("          ", target[:140])
