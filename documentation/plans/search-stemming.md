# Plan: search finds singular and plural forms

## Goal

A visitor or an editor searches for `livros` and finds the page about "o livro". A search for `animal` finds "animais", and `estação` finds "estações". Today the search index (ADR-0054) matches word beginnings only. `livro` already finds "livros", but `livros` does not find "livro", and plurals that change the end of the word (`animais`, `estações`, `pães`, `homens`) are never found from the singular.

Lynx ships a plural reducer per language, written in PHP. It runs on the index and on the query, so SQLite, PostgreSQL and the `LIKE` engine keep returning the same pages.

## Decisions taken

| Question | Decision |
| --- | --- |
| What gets reduced | Plurals only. A reducer turns a plural into its singular (`animais` into `animal`). Verb forms, gender and derivations stay as they are. Plural reduction merges few unrelated words, so search results stay predictable. |
| Where the rules live | Hand-written PHP classes in core, one per language, with their own tests. No Composer dependency, no PHP extension, no generated code. Shared hosting keeps working. |
| Which languages ship | Portuguese first. English and French follow in the same release if time allows. Other languages come as pull requests with tests. A language without a reducer searches as it does today. |
| How a language finds its reducer | By `Language::appLocale()`: the full locale first (`pt-BR`), then the bare language (`pt`). The default language with the code `default` uses `lynx.base_locale`. |
| Public API | `WordReducer` (`@api`): `reduce(string $word): string`. `WordReducers` (`@api`), a registry in core like `PageGates` and `PageVisibility` (ADR-0062): `register(string $locale, WordReducer $reducer)` and `for(Language $language): ?WordReducer`. A site adds or replaces a reducer in its own service provider. |
| What the index holds | Each folded word, followed by the folded reduced form when it differs: `livros livro sobre animais animal`. Duplicates are dropped. |
| How a query matches | Each query word matches its own beginning or its reduced form exactly: `("livros"* OR "livro")` on SQLite, `(livros:* \| livro)` on PostgreSQL. The reduced form is never a prefix, so `livro` does not reach "livraria" through it. |
| Order of steps | Lowercase, reduce, then fold accents. The rules read accents (`-ões`, `-ães`, `-éis`). Folding comes last, so `estação` and `estacao` still meet. |
| Short words | Words under 4 letters are never reduced, so a search for `mes` while typing "mesa" does not also match the word "me". |
| Wrong reductions | A rule that misfires usually yields a non-word (`inglês` into `inglê`), which matches nothing and costs nothing. The tests cover the misfires that land on a real word (`mães` into `mão`), and the exceptions list fixes them. |
| Switching it off | `lynx.search.reduce_plurals` (env `LYNX_SEARCH_REDUCE_PLURALS`), on by default. Off, queries and new index rows use words only. |
| Existing indexes | Need `lynx:search-index` once. Until then, queries still find every page they find today: the reduced alternative only adds matches. |

## Part 1: the reducer and its registry

1. **`LynxCms\Core\Search\Plurals\WordReducer`** (`@api`, interface). The word arrives lowercased with its accents, holding letters and digits only. A reducer returns the singular, or the word unchanged.
2. **`WordReducers`** (`@api`, registry). Bound as a singleton in `SearchServiceProvider` with the built-in reducers. `for()` returns `null` when `lynx.search.reduce_plurals` is off or no reducer matches the language.
3. **`PortuguesePlurals`** (`@internal`, final). Rules apply to the longest matching ending first, and only one rule applies per word:

| Ending | Becomes | Examples |
| --- | --- | --- |
| `-ões`, `-ães`, `-ãos` | `-ão` | estações, pães, mãos |
| `-ais` | `-al` | animais, jornais |
| `-éis` | `-el` | papéis, hotéis |
| `-eis` | `-il` | fáceis, úteis |
| `-óis` | `-ol` | lençóis, faróis |
| `-uis` | `-ul` | azuis |
| `-is` (unaccented) | `-il` | funis, barris |
| `-ns` | `-m` | homens, jardins |
| `-res`, `-zes`, `-ses`, `-les` | drop `-es` | flores, luzes, meses, males |
| `-s` | drop `-s` | livros, casas, cafés |

   Exceptions are a short list of words returned as they are or mapped directly: invariable words (`lápis`, `pires`, `vírus`, `ónibus`, `ônibus`, `oásis`, `atlas`) and irregular plurals (`mães` into `mãe`). The list starts with the words the tests need and grows with reports.

4. **`EnglishPlurals`** and **`FrenchPlurals`**, if they make the release: `-ies` into `-y`, `-es` after `s`, `x`, `z`, `ch`, `sh`, and `-s` for English; `-aux` into `-al`, `-eaux` into `-eau`, and `-s` or `-x` for French, each with its own exceptions.

## Part 2: the index and the query

- **`SearchText`** splits into steps: `tokens()` (markup removed, lowercased, accents kept), `fold()` (accents removed) and `words()` (both, as today, so existing callers keep working). A new `terms(string $text, ?WordReducer $reducer)` returns the folded words with their folded reduced forms.
- **`SearchText::of()`** looks up the reducer for the language it already receives and writes `terms()` to the row.
- **`SearchIndex::whereMatches()`** finds the language from `$languageId` through `LanguageRegistry`, which it already holds, and passes the engine a list of `SearchWord` values (`@internal`, readonly: `prefix`, `?reduced`).
- **Engines** take `non-empty-list<SearchWord>`:
  - SQLite: one FTS5 group per word, `("livros"* OR "livro")`, joined with spaces (every group must match).
  - PostgreSQL: `(livros:* | livro) & (...)` in `to_tsquery('simple', ?)`.
  - `LIKE`: `(' ' || content || ' ') LIKE '% livros%' OR ... LIKE '% livro %'`, one condition per word.
  - Words still hold only letters and digits after folding, so the SQL stays built from safe terms (ADR-0054). Values keep going through bindings.
- The admin's search box, the Page Finder and `search*=` all go through `whereMatches()`, so they gain plurals with no changes of their own.

## Part 3: docs and records

- **ADR-0119**, amending ADR-0054: plural reduction, the registry, the query shape and the short-word rule.
- `docs/basics/selectors.md`, "Searching text": `livros` finds "livro", and `animal` finds "animais". Name the languages that ship.
- `docs/basics/languages.md`: a short section on adding a reducer for a site's language, with a tested snippet in `docs/snippets`.
- `docs/reference/configuration.md`: `lynx.search.reduce_plurals`.
- Release notes and the upgrade guide: run `php artisan lynx:search-index` once after updating.

## Tests (TDD, SQLite and PostgreSQL)

Unit tests, written before each class:

- `tests/Unit/Search/PortuguesePluralsTest.php`: a dataset of plural and singular pairs, one per rule in the table above. Words returned unchanged: singulars (`livro`, `animal`, `estação`), invariable words (`lápis`, `vírus`), words under 4 letters. Irregular plurals from the exceptions list (`mães`).
- `tests/Unit/Search/WordReducersTest.php`: `pt-PT` and `pt-BR` find `pt`, a registered `pt-BR` wins over `pt`, the `default` language uses `lynx.base_locale`, an unknown locale returns `null`, the setting off returns `null`, and a site's reducer replaces a built-in one.
- `SearchText` terms: the index row for "Livros sobre animais" holds `livros livro sobre animais animal`. Accents are folded after reduction. No duplicates.

Feature tests in `tests/Feature/Search/SearchPluralsTest.php`, on a Portuguese site built the way `SearchIndexTest.php` builds one:

- `livros` finds a page with "livro", and `livro` finds a page with "livros".
- `animal` and `animais`, `estação` and `estações` (also typed without accents), `pão` and `pães`, `homem` and `homens` find each other.
- Prefixes still work while typing: `estaç` finds "estações".
- A short word is not reduced: `mes` does not find a page holding only "me".
- `mães` finds "mãe" and does not find "mão".
- A language without a reducer behaves as today.
- With `lynx.search.reduce_plurals` off, `livros` does not find "livro".
- Fallback text: an English sentence in a Portuguese row is still found by its own words.
- An index built before this change still finds what it found before, and finds plurals after `lynx:search-index`.
- The `LIKE` engine passes the same cases (bind `LikeSearchEngine`, as `SearchIndexTest.php` does).

Benchmarks: run `lynx:bench` search cases before and after, on SQLite and PostgreSQL, and record index size and query times in the ADR.

## Order of work

1. ADR-0119 draft, so the review covers the decisions before the code.
2. `WordReducer`, `WordReducers` and `PortuguesePlurals`, unit tests first.
3. `SearchText` split and `terms()`, unit tests first.
4. `SearchWord` and the three engines, with the feature tests on SQLite, then PostgreSQL.
5. The setting, the docs, the release notes and the upgrade guide entry.
6. English and French reducers, if they fit the release.
7. A pull request: the change touches stored index content and adds public API.

## Out of scope

- Full stemming (verb forms, derivations such as `organização` and `organizar`). The registry accepts a fuller reducer later, such as Snowball algorithms generated to PHP, with no change to the engines.
- Synonyms and spelling mistakes.
- PostgreSQL language configurations (`to_tsvector('portuguese', ...)`). They would make PostgreSQL find pages SQLite does not.
- Ranking results by relevance.
