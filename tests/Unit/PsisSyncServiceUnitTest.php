<?php

namespace Tests\Unit;

use App\Services\PsisSyncService;
use PHPUnit\Framework\TestCase;

class PsisSyncServiceUnitTest extends TestCase
{
    private PsisSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PsisSyncService;
    }

    public function test_extract_tuples_handles_single_insert_tuple(): void
    {
        $line = "INSERT INTO `rms_branch` VALUES (1,'Branch 1',NULL,1,'សាខា ១','CHV','Address',NULL,'012345678',1,1,1,1,1);";
        $tuples = iterator_to_array($this->service->extractTuples($line));

        $this->assertCount(1, $tuples);
        $this->assertSame("1,'Branch 1',NULL,1,'សាខា ១','CHV','Address',NULL,'012345678',1,1,1,1,1", $tuples[0]);
    }

    public function test_extract_tuples_handles_multiple_tuples_in_one_statement(): void
    {
        $line = "INSERT INTO `tbl_group` VALUES (101,'Class 10A',1),(102,'Class 10B',2);";
        $tuples = iterator_to_array($this->service->extractTuples($line));

        $this->assertCount(2, $tuples);
        $this->assertSame("101,'Class 10A',1", $tuples[0]);
        $this->assertSame("102,'Class 10B',2", $tuples[1]);
    }

    public function test_extract_tuples_handles_parentheses_and_commas_inside_quotes(): void
    {
        $line = "INSERT INTO `test` VALUES (1,'Name with (parentheses) and , commas',42);";
        $tuples = iterator_to_array($this->service->extractTuples($line));

        $this->assertCount(1, $tuples);
        $this->assertSame("1,'Name with (parentheses) and , commas',42", $tuples[0]);
    }

    public function test_extract_tuples_handles_escaped_quotes_inside_strings(): void
    {
        $line = "INSERT INTO `test` VALUES (1,'O\'Connor',10),(2,'John',20);";
        $tuples = iterator_to_array($this->service->extractTuples($line));

        $this->assertCount(2, $tuples);
        $this->assertSame("1,'O\'Connor',10", $tuples[0]);
        $this->assertSame("2,'John',20", $tuples[1]);
    }

    public function test_parse_sql_values_correctly_types_integers_floats_strings_and_nulls(): void
    {
        $tuple = "1, 'Hello World', 3.14, NULL, 'Another String', 0";
        $values = $this->service->parseSqlValues($tuple);

        $this->assertSame(1, $values[0]);
        $this->assertSame('Hello World', $values[1]);
        $this->assertSame(3.14, $values[2]);
        $this->assertNull($values[3]);
        $this->assertSame('Another String', $values[4]);
        $this->assertSame(0, $values[5]);
    }

    public function test_parse_sql_values_handles_khmer_unicode_text(): void
    {
        $tuple = "105, 'សុខ សាន', 'សិស្សឆ្នើម', '0961234567'";
        $values = $this->service->parseSqlValues($tuple);

        $this->assertSame(105, $values[0]);
        $this->assertSame('សុខ សាន', $values[1]);
        $this->assertSame('សិស្សឆ្នើម', $values[2]);
        $this->assertSame('0961234567', $values[3]);
    }

    public function test_parse_sql_values_handles_doubled_single_quotes_escape(): void
    {
        $tuple = "1, 'It''s a sunny day', 'Done'";
        $values = $this->service->parseSqlValues($tuple);

        $this->assertSame(1, $values[0]);
        $this->assertSame("It's a sunny day", $values[1]);
        $this->assertSame('Done', $values[2]);
    }

    public function test_parse_sql_values_handles_empty_string(): void
    {
        $tuple = "1, '', NULL";
        $values = $this->service->parseSqlValues($tuple);

        $this->assertSame(1, $values[0]);
        $this->assertSame('', $values[1]);
        $this->assertNull($values[2]);
    }
}
