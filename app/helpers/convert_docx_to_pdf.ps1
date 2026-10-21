param(
    [Parameter(Mandatory=$true)]
    [string]$docxPath,

    [Parameter(Mandatory=$true)]
    [string]$pdfPath
)

$ErrorActionPreference = "Stop"

if (!(Test-Path $docxPath)) {
    Write-Error "Source Word document not found: $docxPath"
    exit 1
}

# Ensure destination directory exists
$destDir = Split-Path $pdfPath -Parent
if (!(Test-Path $destDir)) {
    New-Item -ItemType Directory -Path $destDir -Force | Out-Null
}

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0

try {
    # Open Word document read-only
    $doc = $word.Documents.Open($docxPath, $false, $true, $false)
    
    # 17 = wdFormatPDF
    $doc.SaveAs2($pdfPath, 17)
    $doc.Close([ref]$false)
    
    Write-Host "SUCCESS: Saved high-fidelity PDF to $pdfPath"
    exit 0
}
catch {
    Write-Error "Word COM Export to PDF Error: $_"
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
