import { validateMachineNameClientSide } from '@/features/validation/validation';

describe('validateMachineNameClientSide', () => {
  it('should accept valid machine names', () => {
    expect(validateMachineNameClientSide('valid_name')).to.equal('');
    expect(validateMachineNameClientSide('valid-name')).to.equal('');
    expect(validateMachineNameClientSide('valid name123')).to.equal('');
    expect(validateMachineNameClientSide('Valid name')).to.equal('');
  });

  it('should reject names starting with a number', () => {
    expect(validateMachineNameClientSide('1invalid')).to.equal(
      'Name cannot start with a number',
    );
    expect(validateMachineNameClientSide('42foo')).to.equal(
      'Name cannot start with a number',
    );
  });

  it('should reject names with invalid patterns', () => {
    const errorMsg =
      'Special characters are not allowed. Name cannot start or end with a hyphen, underscore, or whitespace.';
    expect(validateMachineNameClientSide('name@with!special')).to.equal(
      errorMsg,
    );
    expect(validateMachineNameClientSide('-name')).to.equal(errorMsg);
    expect(validateMachineNameClientSide('name ')).to.equal(errorMsg);
    expect(validateMachineNameClientSide('_name')).to.equal(errorMsg);
    expect(validateMachineNameClientSide('name_')).to.equal(errorMsg);
  });
});
