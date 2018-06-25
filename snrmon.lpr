Program snrmon;
Uses
  {$IFDEF debug}
  SysUtils,
  {$ENDIF}
  {$IFDEF UNIX}
  CThreads,
  {$ENDIF}
  DaemonApp, lazdaemonapp, daemonmapp, daemonmain, snrmonthreads, zcore,
  laz_synapse
  { add your units here };

begin
  {$IFDEF DEBUG}
  // Assuming your build mode sets -dDEBUG in Project Options/Other when defining -gh
  // This avoids interference when running a production/default build without -gh

  // Set up -gh output for the Leakview package:
  if FileExists('d:\heap.trc') then
    DeleteFile('d:\heap.trc');
  SetHeapTraceOutput('d:\heap.trc');
  {$ENDIF DEBUG}
  Application.Initialize;
  Application.Run;
end.
