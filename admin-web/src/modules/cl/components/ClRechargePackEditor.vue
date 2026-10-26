<script setup lang="ts">
import { ref, watch } from 'vue'

/**
 * 充值档位编辑器（`recharge.packs`）。
 *
 * 数据库里仍然是 JSON 数组字符串（`[{"coins":60,"yuan":6}, ...]`），这里只把编辑方式
 * 换成表格：每行「金币 / 价格（元）」，可增删，保存时按当前顺序序列化。
 *
 * 和 `ClTierEditor` 一样兼容历史双重编码的数据（字符串里再套一层数组）。
 */
defineOptions({ name: 'ClRechargePackEditor' })

const props = withDefaults(defineProps<{
  modelValue?: string
}>(), {
  modelValue: '[]',
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

interface RechargePack {
  coins: number
  yuan: number
}

const rows = ref<RechargePack[]>([])

/** 解一层，兼容 `"[{...}]"` 这种双重编码。 */
function decode(raw: string): unknown {
  try {
    let parsed: unknown = JSON.parse(raw || '[]')
    if (typeof parsed === 'string') {
      parsed = JSON.parse(parsed)
    }

    return parsed
  }
  catch {
    return null
  }
}

function parsePacks(parsed: unknown): RechargePack[] {
  if (!Array.isArray(parsed)) {
    return []
  }

  return parsed.map((item) => {
    const pack = item as { coins?: unknown, yuan?: unknown }

    return {
      coins: Math.max(1, Math.round(Number(pack?.coins ?? 1))),
      yuan: Math.max(1, Math.round(Number(pack?.yuan ?? 1))),
    }
  })
}

const invalid = ref(false)

watch(
  () => props.modelValue,
  (raw) => {
    const parsed = decode(raw)
    invalid.value = !Array.isArray(parsed)
    rows.value = parsePacks(parsed)
  },
  { immediate: true },
)

function commit(next: RechargePack[]) {
  rows.value = next
  emit('update:modelValue', JSON.stringify(next))
}

function update(index: number, key: 'coins' | 'yuan', value: number | undefined) {
  const next = rows.value.map((row, i) => i === index
    ? { ...row, [key]: Math.max(1, Math.round(Number(value ?? 1))) }
    : row)
  commit(next)
}

function add() {
  commit([...rows.value, { coins: 60, yuan: 6 }])
}

function remove(index: number) {
  commit(rows.value.filter((_, i) => i !== index))
}

function onRawInput(value: string) {
  emit('update:modelValue', value)
}
</script>

<template>
  <div class="min-w-0 flex-1">
    <template v-if="!invalid">
      <div v-if="rows.length === 0" class="text-xs text-gray-400">
        还没有档位，添加一个后 App 充值页才会显示
      </div>

      <div
        v-for="(row, index) in rows"
        :key="index"
        class="mb-2 flex flex-wrap items-center gap-2"
      >
        <el-input-number
          :model-value="row.coins"
          :min="1"
          controls-position="right"
          @update:model-value="(v: number | undefined) => update(index, 'coins', v)"
        />
        <span class="text-xs text-gray-400">金币</span>
        <span class="text-xs text-gray-300">/</span>
        <el-input-number
          :model-value="row.yuan"
          :min="1"
          controls-position="right"
          @update:model-value="(v: number | undefined) => update(index, 'yuan', v)"
        />
        <span class="text-xs text-gray-400">元</span>
        <el-button type="danger" link @click="remove(index)">
          删除
        </el-button>
      </div>

      <el-button type="primary" plain @click="add">
        添加档位
      </el-button>

      <div class="mt-1 text-xs text-gray-400">
        金币和价格都必须是正整数；App 充值页按这里的顺序显示，保存后立刻生效。
      </div>
    </template>

    <el-input
      v-else
      :model-value="modelValue"
      type="textarea"
      :rows="3"
      @update:model-value="onRawInput"
    />
  </div>
</template>
