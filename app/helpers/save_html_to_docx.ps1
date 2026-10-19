param(
    [Parameter(Mandatory=$true)]
    [string]$htmlPath,

    [Parameter(Mandatory=$true)]
    [string]$docxPath
)

$ErrorActionPreference = "Stop"

$fullHtmlPath = [System.IO.Path]::GetFullPath($htmlPath)
$fullDocxPath = [System.IO.Path]::GetFullPath($docxPath)

if (!(Test-Path $fullHtmlPath)) {
    Write-Error "Source HTML file not found: $fullHtmlPath"
    exit 1
}

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0

try {
    # Open HTML in Word
    $doc = $word.Documents.Open($fullHtmlPath, $false, $false, $false)
    
    # Save as OpenXML DOCX (16 = wdFormatXMLDocument)
    $doc.SaveAs2($fullDocxPath, 16)
    $doc.Close([ref]$false)
    
    Write-Host "SUCCESS: Saved DOCX to $fullDocxPath"
    exit 0
}
catch {
    Write-Error "Word COM Error: $_"
    exit 1
}
finally {
    try {
        $word.Quit()
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
    } catch {}
    [System.GC]::Collect()
    [System.GC]::WaitForPendingFinalizers()
}
