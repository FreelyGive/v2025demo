import transforms from '@/utils/transforms';

describe('Transforms - link', () => {
  const fieldData = {
    sourceTypeSettings: {
      instance: {},
    },
  };

  it('Should return just a URI if title is disabled', () => {
    fieldData.sourceTypeSettings.instance.title = 0;
    expect(
      transforms.link([{ uri: 'https://example.com' }], {}, fieldData),
    ).to.equal('https://example.com');
  });

  it('Should return URI and title if title is enabled', () => {
    fieldData.sourceTypeSettings.instance.title = 2;
    expect(
      transforms.link(
        [{ uri: 'https://example.com', title: 'Click me' }],
        {},
        fieldData,
      ),
    ).to.deep.equal({ uri: 'https://example.com', title: 'Click me' });
  });

  it('Should match on autocomplete, no title', () => {
    fieldData.sourceTypeSettings.instance.title = 0;
    expect(
      transforms.link([{ uri: 'A node title (3)' }], {}, fieldData),
    ).to.equal('entity:node/3');
  });

  it('Should match on autocomplete, with title', () => {
    fieldData.sourceTypeSettings.instance.title = 2;
    expect(
      transforms.link(
        [{ uri: 'A node title (3)', title: 'Click me' }],
        {},
        fieldData,
      ),
    ).to.deep.equal({ uri: 'entity:node/3', title: 'Click me' });
  });
});
