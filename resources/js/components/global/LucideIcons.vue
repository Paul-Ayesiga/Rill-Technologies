<script>
import { defineComponent, h } from 'vue';
import * as LucideIcons from 'lucide-vue-next';

export default defineComponent({
  name: 'LucideIcons',
  setup() {
    return () => h('div', { style: { display: 'none' } }, [
      // Render all Lucide icons as components
      ...Object.entries(LucideIcons).map(([name, component]) => {
        // Skip non-icon exports
        if (name === 'createLucideIcon' || name === 'defaultAttributes') {
          return null;
        }
        return h(component, { key: name });
      })
    ]);
  }
});
</script>
