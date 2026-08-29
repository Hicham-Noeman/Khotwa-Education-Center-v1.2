# RB brand font sources

Put the three licensed RB files here, then run the build from the project root:

    python tools/build-fonts.py

The filename only has to contain the weight - `RB-Light.otf`, `RB Regular.ttf`,
`rb_bold.woff2` all work. Anything else (SemiBold, ExtraBold, Italic) is skipped,
because `assets/css/fonts.css` declares exactly three faces:

| Weight  | CSS `font-weight` | Built as                       |
| ------- | ----------------- | ------------------------------ |
| Light   | 300               | `rb-light.woff2` / `.woff`     |
| Regular | 400               | `rb-regular.woff2` / `.woff`   |
| Bold    | 700               | `rb-bold.woff2` / `.woff`      |

The built files land one folder up in `assets/fonts/` and are what the site serves.
Both these originals and the built copies stay out of the web root's reach except
for the `.woff2`/`.woff` pair, so the licensed originals are never downloadable.
