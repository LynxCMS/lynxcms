<div class="island-assets">
    assets {{ $count }}
    @assets
        <script src="/js/island-chart.js"></script>
    @endassets
    @script
        <script>window.islandScriptRan = true;</script>
    @endscript
</div>
