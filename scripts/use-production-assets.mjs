import { access, unlink } from 'node:fs/promises';
const root = new URL('../', import.meta.url);
await access(new URL('public/build/manifest.json', root));
try {
    await unlink(new URL('public/hot', root));
} catch (error) {
    if (error.code !== 'ENOENT') throw error;
}
console.log('Laravel is using the built production assets. Run npm run dev to return to Vite.');
