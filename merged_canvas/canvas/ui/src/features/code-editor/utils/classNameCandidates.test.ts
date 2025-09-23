import { describe, expect, it } from 'vitest';

import {
  deleteClassNameCandidatesInComment,
  findComment,
  getClassNameCandidatesFromComment,
  upsertClassNameCandidatesInComment,
} from './classNameCandidates';

describe('finding the comment', () => {
  it('returns null if no comment is found', () => {
    expect(findComment('no comment')).toEqual(null);
  });

  it('returns data if comment is found', () => {
    const source =
      '// @classNameCandidates {"card": ["bg-primary", "text-white"]}\n';

    expect(findComment(source)).toEqual({
      parsedData: { card: ['bg-primary', 'text-white'] },
      commentStart: 0,
      commentEnd: source.length - 1,
    });
  });

  it('returns data if comment is found inside a string', () => {
    const comment =
      '// @classNameCandidates {"card": ["bg-primary", "text-white"]}\n';
    const contentBefore = 'some content before\n';
    const contentAfter = 'some content after\n';
    const source = `${contentBefore}${comment}${contentAfter}`;

    expect(findComment(source)).toEqual({
      parsedData: { card: ['bg-primary', 'text-white'] },
      commentStart: contentBefore.length,
      commentEnd: contentBefore.length + comment.length - 1,
    });
  });
});

describe('getting class names', () => {
  it('returns empty array if no comment is found', () => {
    expect(getClassNameCandidatesFromComment('no comment')).toEqual([]);
  });

  it('returns class names if a comment is found', () => {
    expect(
      getClassNameCandidatesFromComment(
        '// @classNameCandidates {"card": ["bg-primary", "text-white"]}\n',
      ),
    ).toEqual(['bg-primary', 'text-white']);
  });

  it('returns unique class names', () => {
    expect(
      getClassNameCandidatesFromComment(
        '// @classNameCandidates {"card": ["bg-primary", "text-white"], "button": ["font-bold", "text-white", "text-white"]}\n',
      ),
    ).toEqual(['bg-primary', 'text-white', 'font-bold']);
  });
});

describe('upserting class names', () => {
  describe('when no comment is found', () => {
    it('adds comment if source is empty', () => {
      expect(
        upsertClassNameCandidatesInComment('', 'card', [
          'bg-primary',
          'text-white',
        ]),
      ).toEqual({
        nextSource:
          '// @classNameCandidates {"card":["bg-primary","text-white"]}\n',
        nextClassNameCandidates: ['bg-primary', 'text-white'],
      });
    });

    it('adds comment to the beginning of the source', () => {
      const content = 'some content\n';
      expect(
        upsertClassNameCandidatesInComment(content, 'card', [
          'bg-primary',
          'text-white',
        ]),
      ).toEqual({
        nextSource: `// @classNameCandidates {"card":["bg-primary","text-white"]}\n${content}`,
        nextClassNameCandidates: ['bg-primary', 'text-white'],
      });
    });
  });

  describe('with existing comment', () => {
    it('extends component', () => {
      const content =
        '// @classNameCandidates {"card":["bg-primary","text-white"]}\n';
      expect(
        upsertClassNameCandidatesInComment(content, 'card', [
          'bg-primary',
          'text-white',
          'text-xl',
        ]),
      ).toEqual({
        nextSource:
          '// @classNameCandidates {"card":["bg-primary","text-white","text-xl"]}\n',
        nextClassNameCandidates: ['bg-primary', 'text-white', 'text-xl'],
      });
    });

    it('adds new component', () => {
      const content =
        '// @classNameCandidates {"card":["bg-primary","text-white"]}\n';
      expect(
        upsertClassNameCandidatesInComment(content, 'button', [
          'text-xl',
          'text-red-500',
        ]),
      ).toEqual({
        nextSource:
          '// @classNameCandidates {"card":["bg-primary","text-white"],"button":["text-xl","text-red-500"]}\n',
        nextClassNameCandidates: [
          'bg-primary',
          'text-white',
          'text-xl',
          'text-red-500',
        ],
      });
    });

    it('properly re-inserts content', () => {
      const content =
        'some content before\n// @classNameCandidates {"card":["bg-primary","text-white"]}\nand some content after\n';
      expect(
        upsertClassNameCandidatesInComment(content, 'button', [
          'text-xl',
          'text-white',
        ]),
      ).toEqual({
        nextSource:
          'some content before\n// @classNameCandidates {"card":["bg-primary","text-white"],"button":["text-xl","text-white"]}\nand some content after\n',
        nextClassNameCandidates: ['bg-primary', 'text-white', 'text-xl'],
      });
    });
  });
});

describe('deleting class names', () => {
  it('returns source if no comment is found', () => {
    expect(deleteClassNameCandidatesInComment('no comment', 'card')).toBe(
      'no comment',
    );
  });

  it('returns source if component is not found', () => {
    const content =
      '// @classNameCandidates {"card":["bg-primary","text-white"],"button":["text-xl","text-red-500"]}\n';
    expect(deleteClassNameCandidatesInComment(content, 'hero')).toBe(content);
  });

  it('deletes component', () => {
    const content =
      '// @classNameCandidates {"card":["bg-primary","text-white"],"button":["text-xl","text-red-500"]}\n';
    expect(deleteClassNameCandidatesInComment(content, 'button')).toBe(
      '// @classNameCandidates {"card":["bg-primary","text-white"]}\n',
    );
  });

  it('properly re-inserts content', () => {
    const content = `some content before\n// @classNameCandidates {"card":["bg-primary","text-white"],"button":["text-xl","text-red-500"]}\nand some content after\n`;
    expect(deleteClassNameCandidatesInComment(content, 'button')).toBe(
      'some content before\n// @classNameCandidates {"card":["bg-primary","text-white"]}\nand some content after\n',
    );
  });
});
