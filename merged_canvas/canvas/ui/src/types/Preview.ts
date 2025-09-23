export interface viewportSize {
  name: string;
  id: string;
  width: number;
  height: number;
}
export const viewportSizes: viewportSize[] = [
  { name: 'Large Desktop', id: 'large_desktop', width: 2560, height: 1440 },
  { name: 'Desktop', id: 'desktop', width: 1920, height: 1080 },
  { name: 'Tablet', id: 'tablet', width: 1024, height: 768 },
  { name: 'Mobile', id: 'mobile', width: 468, height: 800 },
];
