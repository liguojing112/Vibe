<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * 后台的 Excel 导出（docs/07 第 3.2、3.3 节）。
 *
 * 只做一件小事：**把一张表写成 xlsx 临时文件，把路径交回去**，
 * 由控制器用 `ResponseInterface::download()` 发给浏览器。
 * 不在这里直接返回响应 —— 这样它能在单测里被调用（不用起 HTTP）。
 *
 * 导出量都不大（用户列表一页几百行、批量开号一次最多 200 个），所以整表放内存里写，
 * 不做流式分片。
 */
final class ExcelExporter
{
    /**
     * @param string $sheetName 工作表名（Excel 限制 31 个字符）
     * @param string[] $headers 表头
     * @param array<int, array<int, null|float|int|string>> $rows 每行一个数组，顺序要和表头对上
     * @return string 临时文件的绝对路径（调用方负责在响应发完后删掉）
     */
    public function write(string $sheetName, array $headers, array $rows): string
    {
        $path = $this->tempPath();

        $writer = new Writer(new Options());
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(mb_substr($sheetName, 0, 31));
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    /**
     * 清掉过期的临时文件。
     *
     * 不在响应发完之后删文件 —— `Response::download()` 拿到的是**延迟读取**的文件流，
     * 那会儿下载还没真正开始，删了就把这次下载弄坏了。所以改成「下次导出时顺手清旧的」：
     * 正常路径下文件当天就没了，下载中途失败留下的垃圾也攒不住。
     */
    public function purgeOld(int $minutes = 60): void
    {
        $dir = $this->tempDir();
        $deadline = time() - $minutes * 60;

        foreach (glob($dir . '/cl-export-*.xlsx') ?: [] as $file) {
            if (filemtime($file) < $deadline) {
                @unlink($file);
            }
        }
    }

    private function tempPath(): string
    {
        return $this->tempDir() . '/cl-export-' . bin2hex(random_bytes(8)) . '.xlsx';
    }

    private function tempDir(): string
    {
        $dir = BASE_PATH . '/runtime/export';
        if (! is_dir($dir) && ! mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            throw new RuntimeException('导出的临时目录建不出来：' . $dir);
        }

        return $dir;
    }
}
