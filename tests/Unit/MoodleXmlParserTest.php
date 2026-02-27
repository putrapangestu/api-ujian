<?php

use App\Services\QuestionBankService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('moodle xml parser extracts files, tags, and replaces pluginfile references', function () {
    Storage::fake('public');

    $imageContent = "fake-image-content\n";
    $imageBase64 = chunk_split(base64_encode($imageContent));

    $xml = <<<XML
<quiz>
  <question type="multichoice">
    <questiontext format="html">
      <text><![CDATA[<p>Soal 1</p><p><img src="@@PLUGINFILE@@/img.png" /></p>]]></text>
      <file name="img.png" encoding="base64">{$imageBase64}</file>
    </questiontext>
    <tags>
      <tag><text>Group Satu</text></tag>
    </tags>
    <answer fraction="100">
      <text><![CDATA[<p>Jawaban A</p>]]></text>
    </answer>
    <answer fraction="0">
      <text><![CDATA[<p>Jawaban B</p>]]></text>
    </answer>
  </question>
</quiz>
XML;

    $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    expect($root)->not->toBeFalse();

    $questionNode = $root->question[0];
    $service = new QuestionBankService;

    $extractFiles = new ReflectionMethod($service, 'extractMoodleFiles');
    $extractFiles->setAccessible(true);
    $files = $extractFiles->invoke($service, $questionNode);

    expect($files)->toHaveKey('img.png');
    expect($files['img.png'])->toBe($imageContent);

    $getFirstTag = new ReflectionMethod($service, 'getFirstMoodleTag');
    $getFirstTag->setAccessible(true);
    $tag = $getFirstTag->invoke($service, $questionNode);
    expect($tag)->toBe('Group Satu');

    $replace = new ReflectionMethod($service, 'replacePluginFileReferences');
    $replace->setAccessible(true);
    $html = $replace->invoke($service, 'test-question-id', (string) $questionNode->questiontext->text, $files);

    expect($html)->toContain('/storage/questions/moodle/test-question-id/img.png');
    Storage::disk('public')->assertExists('questions/moodle/test-question-id/img.png');
});
