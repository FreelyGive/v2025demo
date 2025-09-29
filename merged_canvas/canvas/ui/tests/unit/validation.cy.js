import { validateCodeMachineNameClientSide } from '@/features/validation/validation';

describe('validateCodeMachineNameClientSide', () => {
  it('should accept valid machine names', () => {
    expect(validateCodeMachineNameClientSide('valid_name')).to.equal('');
    expect(validateCodeMachineNameClientSide('valid-name')).to.equal('');
    expect(validateCodeMachineNameClientSide('valid name123')).to.equal('');
    expect(validateCodeMachineNameClientSide('Valid name')).to.equal('');
  });

  it('should reject names starting with a number', () => {
    expect(validateCodeMachineNameClientSide('1invalid')).to.equal(
      'Name cannot start with a number',
    );
    expect(validateCodeMachineNameClientSide('42foo')).to.equal(
      'Name cannot start with a number',
    );
  });

  it('should reject names with invalid patterns', () => {
    const errorMsg =
      'Special characters are not allowed. Name cannot start or end with a hyphen, underscore, or whitespace.';
    expect(validateCodeMachineNameClientSide('name@with!special')).to.equal(
      errorMsg,
    );
    expect(validateCodeMachineNameClientSide('-name')).to.equal(errorMsg);
    expect(validateCodeMachineNameClientSide('name ')).to.equal(errorMsg);
    expect(validateCodeMachineNameClientSide('_name')).to.equal(errorMsg);
    expect(validateCodeMachineNameClientSide('name_')).to.equal(errorMsg);
  });
});
