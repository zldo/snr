unit daemonmain;

{$mode objfpc}{$H+}

interface

uses
  Classes, SysUtils, FileUtil, DaemonApp, snrmonthreads, eventlog;


type

  { TSnrMonDaemon }

  TSnrMonDaemon = class(TDaemon)
    procedure DataModuleCreate(Sender: TObject);
    procedure DataModuleStart(Sender: TCustomDaemon; var OK: Boolean);
    procedure DataModuleStop(Sender: TCustomDaemon; var OK: Boolean);
  private
    { private declarations }
    FMonThread: TSNRERDMon;
  public
    { public declarations }
  end;

var
  SnrMonDaemon: TSnrMonDaemon;

implementation

procedure RegisterDaemon;
begin
  RegisterDaemonClass(TSnrMonDaemon)
end;

{$R *.lfm}

{ TSnrMonDaemon }

procedure TSnrMonDaemon.DataModuleCreate(Sender: TObject);
begin
  Logger.LogType := ltFile;
  {$IFDEF UNIX}
  Logger.FileName := '/var/log/snrmon.log';
  {$ELSE}
  Logger.FileName := ChangeFileExt(Application.ExeName, '.log');
  {$ENDIF}
  //FMonThread := TSNRERDMon.Create('', Logger);
  //FMonThread.Resume;
  //Sleep(5000);
  //FMonThread.Free;
end;

procedure TSnrMonDaemon.DataModuleStart(Sender: TCustomDaemon; var OK: Boolean);
begin
  Ok := true;
  {$IFDEF UNIX}
  FMonThread := TSNRERDMon.Create('/etc/snrmon/config.ini', Logger);
  {$ELSE}
  FMonThread := TSNRERDMon.Create(ChangeFileExt(Application.ExeName, '.ini'), Logger);
  {$ENDIF}
  FMonThread.Suspended := false;
end;

procedure TSnrMonDaemon.DataModuleStop(Sender: TCustomDaemon; var OK: Boolean);
begin
  Ok := true;
  Logger.Info('Do MonThread.Free');
  FMonThread.Free;
  Logger.Info('MonThread.Free - Ok');
end;

{ TSnrMonDaemon }



initialization
  RegisterDaemon;
end.

