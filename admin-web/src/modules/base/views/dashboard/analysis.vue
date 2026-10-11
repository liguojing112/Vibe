<!--
 - MineAdmin is committed to providing solutions for quickly building web applications
 - Please view the LICENSE file that was distributed with this source code,
 - For the full copyright and license information.
 - Thank you very much for using MineAdmin.
 -
 - @Author X.Mo<root@imoi.cn>
 - @Link   https://github.com/mineadmin
-->
<script setup lang="tsx">
import AnalysisItem from './components/analysis/analysis-item.vue'
import AnalysisContentPublish from './components/analysis/analysis-content-publish.vue'
import AnalysisHotAuthor from './components/analysis/analysis-hot-author.vue'
import AnalysisContentTimer from './components/analysis/analysis-content-timer.vue'

defineOptions({ name: 'dashboard:analysis' })
</script>

<template>
  <div class="mine-layout">
    <div class="mine-card">
      <div class="text-base">
        <div>舆情分析</div>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div><AnalysisItem name="visitors" chart-type="line" title="访问总人次" /></div>
        <div><AnalysisItem name="published" chart-type="bar" title="内容发布量" /></div>
        <div><AnalysisItem name="visitors" chart-type="line" title="评论总量" /></div>
        <div><AnalysisItem name="contentTimer" chart-type="pie" title="分享总量" /></div>
      </div>
    </div>

    <div class="xl:flex">
      <AnalysisContentPublish />
      <AnalysisHotAuthor />
    </div>

    <AnalysisContentTimer />
  </div>
</template>

<style>
.echarts-tooltip-diy {
  background:
    linear-gradient(
      304.17deg,
      rgb(253 254 255 / 60%) -6.04%,
      rgb(244 247 252 / 60%) 85.2%
    ) !important;
  border: none !important;

  /* Note: backdrop-filter has minimal browser support */

  border-radius: 6px !important;
  backdrop-filter: blur(10px) !important;

  .content-panel {
    display: flex;
    justify-content: space-between;
    width: 164px;
    height: 32px;
    padding: 0 9px;
    margin-bottom: 4px;
    line-height: 32px;
    background: rgb(255 255 255 / 80%);
    border-radius: 4px;
    box-shadow: 6px 0 20px rgb(34 87 188 / 10%);
  }

  .tooltip-title {
    margin: 0 0 10px;
  }

  p {
    margin: 0;
  }

  .tooltip-title,
  .tooltip-value {
    display: flex;
    align-items: center;
    font-size: 13px;
    font-weight: bold;
    line-height: 15px;
    color: #1d2129;
    text-align: right;
  }

  .tooltip-item-icon {
    display: inline-block;
    width: 10px;
    height: 10px;
    margin-right: 8px;
    border-radius: 50%;
  }
}
</style>
