<?php
namespace mod_codesandbox;

/**
 * Clase para calificación automática (autograding)
 * Compara la salida del estudiante con casos de prueba predefinidos
 */
class grader {
    
    /**
     * Compara la salida del estudiante con la salida esperada
     * 
     * @param string $student_output Salida del código del estudiante
     * @param string $expected_output Salida esperada
     * @return bool true si coinciden
     */
    public static function evaluate($student_output, $expected_output) {
        $clean_student = trim($student_output);
        $clean_expected = trim($expected_output);
        return $clean_student === $clean_expected;
    }

    /**
     * Ejecuta todos los casos de prueba y retorna los resultados
     * 
     * @param string $testcases_json JSON con casos de prueba [{"input": "...", "output": "..."}]
     * @param string $actual_output Salida real del código ejecutado
     * @return array Array de resultados con status de cada test
     */
    public static function run_test_cases($testcases_json, $actual_output) {
        $results = [];
        
        try {
            $testcases = json_decode($testcases_json, true);
            
            if (!is_array($testcases) || empty($testcases)) {
                return ['valid' => false, 'error' => 'Invalid test cases format'];
            }
            
            // Por ahora, comparamos solo con el primer output esperado
            // En una versión más avanzada, necesitaríamos ejecutar cada test case por separado
            foreach ($testcases as $index => $testcase) {
                if (!isset($testcase['output'])) {
                    continue;
                }
                
                $expected = $testcase['output'];
                $description = $testcase['description'] ?? "Test " . ($index + 1);
                
                // Comparar salida
                $passed = self::evaluate($actual_output, $expected);
                
                $results[] = [
                    'test_number' => $index + 1,
                    'description' => $description,
                    'input' => $testcase['input'] ?? '',
                    'expected' => $expected,
                    'actual' => $actual_output,
                    'status' => $passed ? 'passed' : 'failed',
                    'passed' => $passed
                ];
            }
            
            return ['valid' => true, 'results' => $results];
            
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Calcula la calificación final basada en tests aprobados
     * 
     * @param array $test_results Array de resultados de tests
     * @param float $max_grade Calificación máxima posible
     * @return float Calificación calculada
     */
    public static function calculate_final_grade($test_results, $max_grade) {
        $total_tests = count($test_results);
        if ($total_tests === 0) {
            return 0;
        }
        
        $passed = 0;
        foreach ($test_results as $result) {
            if (isset($result['passed']) && $result['passed'] === true) {
                $passed++;
            }
        }

        return round(($passed / $total_tests) * $max_grade, 2);
    }
    
    /**
     * Genera un resumen de resultados de autograding
     * 
     * @param array $test_results Array de resultados
     * @return string Resumen formateado
     */
    public static function generate_summary($test_results) {
        $total = count($test_results);
        $passed = 0;
        
        foreach ($test_results as $result) {
            if (isset($result['passed']) && $result['passed'] === true) {
                $passed++;
            }
        }
        
        return "$passed/$total tests passed";
    }
}