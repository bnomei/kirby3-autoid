/*
 *
 */

panel.plugin('bnomei/autoid', {
    fields: {
      autoid: {
        props: {
          value: String
        },
        template: '<k-text-field v-model="value" name="autoid" label="AutoID" :disabled="true" />'
      }
    }
  });
