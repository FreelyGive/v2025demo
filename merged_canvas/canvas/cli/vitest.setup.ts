import { vi } from 'vitest';

vi.mock('@clack/prompts', () => ({
  text: vi.fn(),
  password: vi.fn(),
  confirm: vi.fn(),
  isCancel: vi.fn(),
  cancel: vi.fn(),
}));
