import fs from 'fs/promises';

export async function fileExists(filePath: string): Promise<boolean> {
  try {
    await fs.access(filePath);
    return true;
  } catch {
    return false;
  }
}

export async function directoryExists(dirPath: string): Promise<boolean> {
  return await fs
    .stat(dirPath)
    .then(() => true)
    .catch(() => false);
}
