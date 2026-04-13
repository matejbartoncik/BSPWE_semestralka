# Jak pracovat s MkDocs

Tento rychlý návod vám ukáže, jak upravovat a rozšiřovat vaši projektovou dokumentaci pomocí nástroje MkDocs.

## Spuštění dokumentace

Dokumentace běží jako kontejner v Dockeru. Pro její spuštění (nebo restartování po pádu) použijte příkaz v kořeni projektu:

```bash
docker-compose up -d docs
```

Webové rozhraní pak najdete na adrese: **http://localhost:8000**

## Jak přidat novou stránku

1. **Vytvořte nový Markdown soubor** ve složce `docs/` (např. `nova_stranka.md`).
2. **Přidejte odkaz do navigace:** Otevřete soubor `mkdocs.yml` v kořenovém adresáři projektu a do sekce `nav:` přidejte nový záznam:

```yaml
nav:
  - Home: index.md
  # ... ostatní stránky ...
  - Můj nový návod: nova_stranka.md
```

Hned jak soubor `mkdocs.yml` uložíte, prohlížeč se sám obnoví a změnu uvidíte.

## Formátování textu (Markdown)

Dokumentace používá běžný formát Markdown. Téma *Material for MkDocs* podporuje navíc spoustu skvělých rozšíření.

### Základní formátování

```markdown
# Nadpis 1 (Hlavní titulek)
## Nadpis 2
### Nadpis 3

Tady je **tučný text** a tady *kurzíva*.

- Odrážka 1
- Odrážka 2
  - Vnořená odrážka

[Odkaz na Google](https://google.com)
```

### Vkládání kódu

Pro bloky kódu používejte trojité zpětné apostrofy (značka \`\`\`). Můžete uvést i jazyk pro zvýraznění syntaxe:

```php
<?php
echo "Hello, MkDocs!";
?>
```

### Upozornění a poznámky (Admonitions)

Téma Material for MkDocs podporuje krásná upozornění. Do textu je přidáte takto:

!!! info "Informační rámeček"
    Tohle je užitečná informace.

!!! warning "Pozor"
    Na toto si dejte velký pozor!

Kód pro jejich vytvoření:
```markdown
!!! info "Informační rámeček"
    Tohle je užitečná informace.

!!! warning "Pozor"
    Na toto si dejte velký pozor!
```

## Další informace

Kompletní možnosti tématu se dočtete v [oficiální dokumentaci Material for MkDocs](https://squidfunk.github.io/mkdocs-material/getting-started/).
