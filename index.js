/*
 *
 */

panel.plugin('bnomei/autoid', {
  fields: {
    autoid: {
      props: {
        value: String,
        label: String,
        help: String,
      },
      template:
        '<k-text-field v-model="value" :label="label" :help="help" name="autoid" :disabled="true" />'
    }
  }
});
