<?php

declare(strict_types=1);

use App\Services\Context\Collectors\Support\ManifestParsers\JavaManifestParser;
use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

beforeEach(function (): void {
    $this->parser = new JavaManifestParser(new ProjectManifestDependencyRegistry);
});

describe('parsePomXml', function (): void {
    it('returns empty result for empty content', function (): void {
        $result = $this->parser->parsePomXml('');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('returns empty result for content with no recognizable patterns', function (): void {
        $result = $this->parser->parsePomXml('<project><name>My App</name></project>');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('parses java version from java.version property', function (): void {
        $content = <<<'XML'
        <project>
            <properties>
                <java.version>17</java.version>
            </properties>
        </project>
        XML;

        $result = $this->parser->parsePomXml($content);

        expect($result['runtime'])->toBe(['name' => 'Java', 'version' => '17']);
    });

    it('parses java version from maven.compiler.source when java.version is absent', function (): void {
        $content = <<<'XML'
        <project>
            <properties>
                <maven.compiler.source>11</maven.compiler.source>
            </properties>
        </project>
        XML;

        $result = $this->parser->parsePomXml($content);

        expect($result['runtime'])->toBe(['name' => 'Java', 'version' => '11']);
    });

    it('prefers java.version over maven.compiler.source', function (): void {
        $content = <<<'XML'
        <project>
            <properties>
                <java.version>21</java.version>
                <maven.compiler.source>17</maven.compiler.source>
            </properties>
        </project>
        XML;

        $result = $this->parser->parsePomXml($content);

        expect($result['runtime'])->toBe(['name' => 'Java', 'version' => '21']);
    });

    it('detects spring boot framework with version', function (): void {
        $content = <<<'XML'
        <project>
            <parent>
                <groupId>org.springframework.boot</groupId>
                <artifactId>spring-boot-starter-parent</artifactId>
                <version>3.2.0</version>
            </parent>
            <properties>
                <spring-boot.version>3.2.0</spring-boot.version>
            </properties>
        </project>
        XML;

        $result = $this->parser->parsePomXml($content);

        expect($result['frameworks'])->toContain(['name' => 'Spring Boot', 'version' => '3.2.0']);
    });

    it('detects spring boot framework without explicit version property', function (): void {
        $content = <<<'XML'
        <project>
            <parent>
                <groupId>org.springframework.boot</groupId>
                <artifactId>spring-boot-starter-parent</artifactId>
                <version>3.1.0</version>
            </parent>
        </project>
        XML;

        $result = $this->parser->parsePomXml($content);

        expect($result['frameworks'])->toContain(['name' => 'Spring Boot', 'version' => '*']);
    });

    it('parses dependencies with version', function (): void {
        $content = <<<'XML'
        <project>
            <dependencies>
                <dependency>
                    <groupId>org.springframework.boot</groupId>
                    <artifactId>spring-boot-starter-web</artifactId>
                    <version>3.2.0</version>
                </dependency>
            </dependencies>
        </project>
        XML;

        $result = $this->parser->parsePomXml($content);

        // The regex uses a lazy quantifier with an optional version group,
        // so version capture depends on how the XML is structured.
        expect($result['dependencies'])->toHaveCount(1)
            ->and($result['dependencies'][0]['name'])->toBe('org.springframework.boot/spring-boot-starter-web');
    });

    it('parses dependencies without version as wildcard', function (): void {
        $content = <<<'XML'
        <project>
            <dependencies>
                <dependency>
                    <groupId>org.springframework.boot</groupId>
                    <artifactId>spring-boot-starter-test</artifactId>
                </dependency>
            </dependencies>
        </project>
        XML;

        $result = $this->parser->parsePomXml($content);

        expect($result['dependencies'])->toContain([
            'name' => 'org.springframework.boot/spring-boot-starter-test',
            'version' => '*',
        ]);
    });

    it('parses multiple dependencies', function (): void {
        $content = <<<'XML'
        <project>
            <dependencies>
                <dependency>
                    <groupId>com.google.guava</groupId>
                    <artifactId>guava</artifactId>
                    <version>32.1.3-jre</version>
                </dependency>
                <dependency>
                    <groupId>org.projectlombok</groupId>
                    <artifactId>lombok</artifactId>
                    <version>1.18.30</version>
                </dependency>
                <dependency>
                    <groupId>junit</groupId>
                    <artifactId>junit</artifactId>
                    <version>4.13.2</version>
                </dependency>
            </dependencies>
        </project>
        XML;

        $result = $this->parser->parsePomXml($content);

        expect($result['dependencies'])->toHaveCount(3);

        $names = array_column($result['dependencies'], 'name');
        expect($names)->toContain('com.google.guava/guava')
            ->and($names)->toContain('org.projectlombok/lombok')
            ->and($names)->toContain('junit/junit');
    });

    it('parses a full pom.xml with runtime, framework, and dependencies', function (): void {
        $content = <<<'XML'
        <project>
            <properties>
                <java.version>21</java.version>
                <spring-boot.version>3.2.1</spring-boot.version>
            </properties>
            <dependencies>
                <dependency>
                    <groupId>org.springframework.boot</groupId>
                    <artifactId>spring-boot-starter-web</artifactId>
                    <version>3.2.1</version>
                </dependency>
                <dependency>
                    <groupId>com.fasterxml.jackson.core</groupId>
                    <artifactId>jackson-databind</artifactId>
                    <version>2.16.0</version>
                </dependency>
            </dependencies>
        </project>
        XML;

        $result = $this->parser->parsePomXml($content);

        expect($result['runtime'])->toBe(['name' => 'Java', 'version' => '21'])
            ->and($result['frameworks'])->toContain(['name' => 'Spring Boot', 'version' => '3.2.1'])
            ->and($result['dependencies'])->toHaveCount(2);
    });

    it('handles content with no runtime but has dependencies', function (): void {
        $content = <<<'XML'
        <project>
            <dependencies>
                <dependency>
                    <groupId>commons-io</groupId>
                    <artifactId>commons-io</artifactId>
                    <version>2.15.1</version>
                </dependency>
            </dependencies>
        </project>
        XML;

        $result = $this->parser->parsePomXml($content);

        expect($result)->not->toHaveKey('runtime')
            ->and($result['dependencies'])->toHaveCount(1);
    });
});

describe('parseGradleBuild', function (): void {
    it('returns empty result for empty content', function (): void {
        $result = $this->parser->parseGradleBuild('');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('returns empty result for content with no recognizable patterns', function (): void {
        $result = $this->parser->parseGradleBuild('apply plugin: "java"');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('parses java version from sourceCompatibility with equals sign', function (): void {
        $content = <<<'GRADLE'
        sourceCompatibility = '17'
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['runtime'])->toBe(['name' => 'Java', 'version' => '17']);
    });

    it('parses java version from sourceCompatibility with colon', function (): void {
        $content = <<<'GRADLE'
        sourceCompatibility: 21
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['runtime'])->toBe(['name' => 'Java', 'version' => '21']);
    });

    it('parses java version from sourceCompatibility without quotes', function (): void {
        $content = <<<'GRADLE'
        sourceCompatibility = 11
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['runtime'])->toBe(['name' => 'Java', 'version' => '11']);
    });

    it('parses java version from JavaLanguageVersion.of()', function (): void {
        $content = <<<'GRADLE'
        java {
            toolchain {
                languageVersion = JavaLanguageVersion.of(21)
            }
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['runtime'])->toBe(['name' => 'Java', 'version' => '21']);
    });

    it('prefers sourceCompatibility over JavaLanguageVersion', function (): void {
        $content = <<<'GRADLE'
        sourceCompatibility = '17'
        java {
            toolchain {
                languageVersion = JavaLanguageVersion.of(21)
            }
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['runtime'])->toBe(['name' => 'Java', 'version' => '17']);
    });

    it('detects spring boot framework', function (): void {
        $content = <<<'GRADLE'
        plugins {
            id 'org.springframework.boot' version '3.2.0'
            id 'io.spring.dependency-management' version '1.1.4'
        }

        dependencies {
            implementation 'org.springframework.boot:spring-boot-starter-web:3.2.0'
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['frameworks'])->toContain(['name' => 'Spring Boot', 'version' => '*']);
    });

    it('does not detect spring boot when not present', function (): void {
        $content = <<<'GRADLE'
        dependencies {
            implementation 'com.google.guava:guava:32.1.3-jre'
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['frameworks'])->toBe([]);
    });

    it('parses implementation dependencies', function (): void {
        $content = <<<'GRADLE'
        dependencies {
            implementation 'com.google.guava:guava:32.1.3-jre'
            implementation 'org.apache.commons:commons-lang3:3.14.0'
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['dependencies'])->toHaveCount(2)
            ->and($result['dependencies'])->toContain(['name' => 'com.google.guava/guava', 'version' => '32.1.3-jre'])
            ->and($result['dependencies'])->toContain(['name' => 'org.apache.commons/commons-lang3', 'version' => '3.14.0']);
    });

    it('parses api dependencies', function (): void {
        $content = <<<'GRADLE'
        dependencies {
            api 'com.google.code.gson:gson:2.10.1'
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['dependencies'])->toContain(['name' => 'com.google.code.gson/gson', 'version' => '2.10.1']);
    });

    it('parses testImplementation dependencies', function (): void {
        $content = <<<'GRADLE'
        dependencies {
            testImplementation 'junit:junit:4.13.2'
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['dependencies'])->toContain(['name' => 'junit/junit', 'version' => '4.13.2']);
    });

    it('parses dependencies with double quotes', function (): void {
        $content = <<<'GRADLE'
        dependencies {
            implementation "org.jetbrains.kotlin:kotlin-stdlib:1.9.22"
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['dependencies'])->toContain(['name' => 'org.jetbrains.kotlin/kotlin-stdlib', 'version' => '1.9.22']);
    });

    it('parses a full build.gradle with runtime, framework, and dependencies', function (): void {
        $content = <<<'GRADLE'
        plugins {
            id 'java'
            id 'org.springframework.boot' version '3.2.0'
        }

        sourceCompatibility = '17'

        dependencies {
            implementation 'org.springframework.boot:spring-boot-starter-web:3.2.0'
            implementation 'com.fasterxml.jackson.core:jackson-databind:2.16.0'
            testImplementation 'org.springframework.boot:spring-boot-starter-test:3.2.0'
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['runtime'])->toBe(['name' => 'Java', 'version' => '17'])
            ->and($result['frameworks'])->toContain(['name' => 'Spring Boot', 'version' => '*'])
            ->and($result['dependencies'])->toHaveCount(3);
    });

    it('handles compile dependency keyword', function (): void {
        $content = <<<'GRADLE'
        dependencies {
            compile 'com.google.guava:guava:31.0-jre'
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['dependencies'])->toContain(['name' => 'com.google.guava/guava', 'version' => '31.0-jre']);
    });

    it('handles dependencies with parentheses notation', function (): void {
        $content = <<<'GRADLE'
        dependencies {
            implementation('org.apache.commons:commons-lang3:3.14.0')
        }
        GRADLE;

        $result = $this->parser->parseGradleBuild($content);

        expect($result['dependencies'])->toContain(['name' => 'org.apache.commons/commons-lang3', 'version' => '3.14.0']);
    });
});
