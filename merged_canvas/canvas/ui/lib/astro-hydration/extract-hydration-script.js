/* Extract the <script/> contents from the Astro generated index.html file which contains
 * Astro's hydration code and write it to /astro-hydration/dist/hydration.js. */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Define the path to the generated index.html
const indexPath = path.join(__dirname, 'dist', 'index.html');
const elementPath = path.join(__dirname, 'dist', 'canvas-island.js');
// Define the path for the output file
const outputFilePath = path.join(__dirname, 'dist', 'hydration.js');

// Read the HTML file
const html = fs.readFileSync(indexPath, 'utf8');

// Regular expression to match <script> tags and capture their contents
const scriptRegex = /<script\b[^>]*>([\s\S]*?)<\/script>/gm;
let match;
let scriptContents = '';

// Iterate over all matches and concatenate their contents
while ((match = scriptRegex.exec(html)) !== null) {
  scriptContents += match[1].trim() + '\n';
}

// Remove when https://github.com/withastro/astro/pull/13046 is merged upstream
scriptContents = scriptContents.replace('{0:t=>', "{'raw':t=>t,0:t=>");

scriptContents = `${scriptContents}\n${fs.readFileSync(elementPath, 'utf8')}\n`;

// Write the script contents to hydration.js
fs.writeFileSync(outputFilePath, scriptContents);
