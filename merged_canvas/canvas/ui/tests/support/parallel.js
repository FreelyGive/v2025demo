import fs from 'fs';
import path from 'path';

const NODE_INDEX = Number(process.env.CI_NODE_INDEX || 1);
const NODE_TOTAL = Number(process.env.CI_NODE_TOTAL || 1);
const TEST_FOLDER = path.join(process.cwd(), 'tests/e2e');

const walk = (dir) => {
  let files = fs.readdirSync(dir);
  files = files.map((file) => {
    const filePath = path.join(dir, file);
    const stats = fs.statSync(filePath);
    if (stats.isDirectory()) {
      return walk(filePath);
    }
    if (stats.isFile() && filePath.match(/\.cy\.js$/)) {
      return filePath;
    }
  });

  return files.reduce((all, folderContents) => all.concat(folderContents), []);
};

const getSpecFiles = () =>
  walk(TEST_FOLDER)
    .sort()
    .filter((item) => !!item)
    .filter((_, index) => index % NODE_TOTAL === NODE_INDEX - 1);

console.log(getSpecFiles().join(','));
