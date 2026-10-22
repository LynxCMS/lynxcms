// Writes dist/admin.js.LICENSES.txt: the license of every npm package bundled into dist/admin.js,
// found through esbuild's metafile. The bundled code is MIT licensed and carries no license
// comments, so the notices ship in this file instead. Sorted, so rebuilds are byte-identical.
import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs'
import { dirname, join } from 'node:path'

const meta = JSON.parse(readFileSync('dist/admin.meta.json', 'utf8'))
const packages = new Map()

for (const input of Object.keys(meta.inputs)) {
    const match = input.match(/node_modules\/((?:@[^/]+\/)?[^/]+)\//)

    if (match && ! packages.has(match[1])) {
        packages.set(match[1], join('node_modules', match[1]))
    }
}

const sections = [...packages.keys()].sort().map((name) => {
    const dir = packages.get(name)
    const manifest = JSON.parse(readFileSync(join(dir, 'package.json'), 'utf8'))
    const file = readdirSync(dir).find((entry) => /^licen[cs]e(\.md|\.txt)?$/i.test(entry))
    const text = file && existsSync(join(dir, file)) ? readFileSync(join(dir, file), 'utf8').trim() : `License: ${manifest.license ?? 'unknown'}`

    return `${name} ${manifest.version}\n${'-'.repeat(name.length + manifest.version.length + 1)}\n${text}\n`
})

writeFileSync(dirname('dist/admin.js') + '/admin.js.LICENSES.txt', `Third-party code bundled in admin.js\n\n${sections.join('\n')}`)
