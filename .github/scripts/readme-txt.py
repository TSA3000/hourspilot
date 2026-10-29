#!/usr/bin/env python3
"""Generate a wordpress.org readme.txt from README.md + the plugin header (used by release.yml for the zip).
Usage: python3 .github/scripts/readme-txt.py README.md hourspilot.php > readme.txt
README.md layout: "# Name", one-line summary, a | **Key** | value | table, then ## sections.
## Features -> == Description ==, ### X -> = X =, attribute tables -> bullet lists, ### Releases (maintainer-only)
and ```mermaid diagrams dropped."""
import re
import sys

DROP = {"Releases"}                       # ### subsections not shipped
RENAME = {"Features": "Description"}


def header(php, key):
    m = re.search(rf"^\s*\*\s*{re.escape(key)}:\s*(.+?)\s*$", php, re.M)
    return m.group(1) if m else ""


def table_rows(lines):
    rows = [[c.strip() for c in l.strip().strip("|").split("|")] for l in lines]
    return [r for r in rows if not all(re.fullmatch(r":?-+:?", c) for c in r if c)]


def main(md_path, php_path):
    md, php = open(md_path, encoding="utf-8").read(), open(php_path, encoding="utf-8").read()
    lines = md.splitlines()
    name = lines[0].lstrip("# ").strip()
    i = 1
    while not lines[i].strip():
        i += 1
    summary = lines[i].strip()

    meta, body, in_meta, skip = {}, [], True, False
    k = i + 1
    while k < len(lines):
        l = lines[k]
        if in_meta and l.startswith("|"):
            for r in table_rows([l]):
                if len(r) == 2 and r[0].startswith("**"):
                    meta[r[0].strip("*")] = r[1]
            k += 1
            continue
        if l.startswith("## "):
            in_meta = False
        if not in_meta:
            body.append(l)
        k += 1

    out, fence, table, diagram = [], False, [], False

    def flush_table():
        rows = table_rows(table)
        if rows:
            head, rows = rows[0], rows[1:]
            for r in rows:
                item, desc = f"* {r[0]}", ""
                for j in range(1, len(r)):
                    if not r[j]:
                        continue
                    if head[j].lower() == "description":
                        desc = r[j]
                    else:
                        item += f" ({head[j].lower()} {r[j]})"
                out.append(f"{item}: {desc}" if desc else item)
        table.clear()

    for l in body:
        if l.startswith("```mermaid"):      # diagrams render on GitHub only
            diagram = True
            continue
        if diagram:
            diagram = not l.startswith("```")
            continue
        if l.startswith("```"):
            fence = not fence
            continue
        if fence:
            out.append("    " + l if l else "")
            continue
        if l.startswith("|"):
            table.append(l)
            continue
        if table:
            flush_table()
        if l.startswith("## "):
            skip = False
            t = l[3:].strip()
            out.append(f"== {RENAME.get(t, t)} ==")
        elif l.startswith("### "):
            t = l[4:].strip()
            skip = t in DROP
            if not skip:
                out.append(f"= {t} =")
        elif not skip:
            out.append(re.sub(r"^- ", "* ", l))
    if table:
        flush_table()

    req = meta.get("Requires", "")
    wp = re.search(r"WordPress\s+([\d.]+)", req)
    phpv = re.search(r"PHP\s+([\d.]+)", req)
    tested = re.search(r"([\d.]+)", meta.get("Tested up to", ""))
    version = header(php, "Version")
    head = [
        f"=== {name} ===",
        f"Contributors: {meta.get('Contributors', '')}",
        f"Tags: {meta.get('Tags', '')}",
        f"Requires at least: {wp.group(1) if wp else header(php, 'Requires at least')}",
        f"Tested up to: {tested.group(1) if tested else ''}",
        f"Requires PHP: {phpv.group(1) if phpv else header(php, 'Requires PHP')}",
        f"Stable tag: {version}",
        f"License: {header(php, 'License')}",
        f"License URI: {header(php, 'License URI')}",
        "",
        summary,
        "",
    ]
    text = "\n".join(head + out)
    text = re.sub(r"\n{3,}", "\n\n", text).strip() + "\n"
    sys.stdout.write(text)


if __name__ == "__main__":
    main(*sys.argv[1:3])
