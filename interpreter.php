<?php

class SimpleInterpreter {
    private $functions = [];
    private $lines = [];          // 执行用行
    private $originalLines = [];  // 包含原始行号
    private $currentLine = 0;

    public function run($code) {
        $rawLines = explode("\n", $code);
        $this->originalLines = [];

        // 保留原始行号和内容
        foreach ($rawLines as $i => $line) {
            if (!$this->isComment($line)) {
                $this->originalLines[] = ['num' => $i, 'text' => rtrim($line)];
            }
        }

        $this->lines = array_map(fn($entry) => mb_strtolower(trim($entry['text'])), $this->originalLines);

        try {
            while ($this->currentLine < count($this->lines)) {
                $line = $this->lines[$this->currentLine];

                if (preg_match('/^function\s+([\p{L}_][\p{L}\p{N}_]*):/u', $line, $matches)) {
                    $this->parseFunction($matches[1]);
                } elseif (preg_match('/^start:/u', $line)) {
                    $this->executeBlock();
                } elseif (trim($line) !== '') {
                    $this->syntaxError("Unexpected line outside of function/start block", $this->currentLine);
                } else {
                    $this->currentLine++;
                }
            }
        } catch (Exception $e) {
            echo "Syntax Error: " . $e->getMessage() . PHP_EOL;
        }
    }

    private function isComment($line) {
        $trimmed = trim($line);
        return $trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with(mb_strtolower($trimmed), 'hello!');
    }

    private function parseFunction($name) {
        $body = [];
        $this->currentLine++;

        while ($this->currentLine < count($this->lines)) {
            $line = $this->lines[$this->currentLine];
            if ($line === 'end') {
                $this->functions[$name] = $body;
                $this->currentLine++;
                return;
            }

            if ($line === '') {
                $this->currentLine++;
                continue;
            }

            $body[] = $line;
            $this->currentLine++;
        }

        $this->syntaxError("Missing 'end' for function '$name'", $this->currentLine - 1);
    }

    private function executeBlock() {
        $this->currentLine++;
        while ($this->currentLine < count($this->lines)) {
            $line = $this->lines[$this->currentLine];

            if ($line === 'end') {
                $this->currentLine++;
                return;
            }

            if ($line === '') {
                $this->currentLine++;
                continue;
            }

            $this->executeLine($line, $this->currentLine);
            $this->currentLine++;
        }

        $this->syntaxError("Missing 'end' for start block", $this->currentLine - 1);
    }

    private function executeLine($line, $lineIndex) {
        if (preg_match('/^say\s+"(.*?)"$/u', $line, $matches)) {
            echo $matches[1] . PHP_EOL;
        } elseif (preg_match('/^([\p{L}_][\p{L}\p{N}_]*)$/u', $line, $matches)) {
            $func = $matches[1];
            if (isset($this->functions[$func])) {
                foreach ($this->functions[$func] as $funcLine) {
                    $this->executeLine($funcLine, -1);
                }
            } else {
                $this->syntaxError("Undefined function '$func'", $lineIndex);
            }
        } else {
            $this->syntaxError("Invalid syntax: '$line'", $lineIndex);
        }
    }

    private function syntaxError($message, $lineIndex) {
        $lineNum = $lineIndex >= 0 && isset($this->originalLines[$lineIndex])
            ? $this->originalLines[$lineIndex]['num'] + 1
            : '?';
        throw new Exception("$message on line $lineNum");
    }
}

// ========== 启动入口 ==========
if ($argc < 2) {
    echo "Usage: php interpreter.php <filename>\n";
    exit(1);
}

$filename = $argv[1];
if (!file_exists($filename)) {
    echo "Error: File '$filename' not found.\n";
    exit(1);
}

$code = file_get_contents($filename);

$interpreter = new SimpleInterpreter();
$interpreter->run($code);
