((Drupal) => {
  Drupal.canvasTransforms.diaclone = (value, options) => {
    const { toCar = false } = options;
    if (toCar && 'car' in value) {
      return value.car;
    }
    if ('robot' in value) {
      return value.robot;
    }
    return null;
  };
})(Drupal)
