import { useSyncExternalStore } from "react";
import { getJobsSnapshot, subscribeJobs } from "./downloadManager";

export function useJobs() {
  return useSyncExternalStore(subscribeJobs, getJobsSnapshot);
}
